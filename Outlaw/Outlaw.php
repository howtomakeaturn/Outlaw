<?php

require_once(realpath(dirname(__FILE__)) . '/src/rb.php');
require_once(__DIR__ . '/src/Valitron/Validator.php');
require_once(__DIR__ . '/src/Exceptions.php');
require_once(__DIR__ . '/src/Helpers.php');
use Valitron\Validator as V;
class Outlaw{
    
    protected $validate;
    protected $errors;
    protected $uploadPath;
    protected $singletonData;
    protected $authData;

    // Transform the $_FILES if it's multiple files into
    // a cleaner format.
    static function reArrayFiles(&$file_post) {

        $file_ary = array();
        $file_count = count($file_post['name']);
        $file_keys = array_keys($file_post);

        for ($i=0; $i<$file_count; $i++) {
            foreach ($file_keys as $key) {
                $file_ary[$i][$key] = $file_post[$key][$i];
            }
        }

        return $file_ary;
    }   
    
    /**
     * Function: sanitize
     * Returns a sanitized string, typically for URLs.
     *
     * Parameters:
     *     $string - The string to sanitize.
     *     $force_lowercase - Force the string to lowercase?
     *     $anal - If set to *true*, will remove all non-alphanumeric characters.
     */
    static function sanitize($file){
        return preg_replace("([^\w\s\d\-_~,;:\[\]\(\].]|[\.]{2,})", '', $file);
    }

    function __construct(){
      
        require_once('config.php');

        R::setup($config['database']['dsn'], $config['database']['user'], $config['database']['password']);    
        
        V::langDir(__DIR__.'/src/Valitron/lang'); // always set langDir before lang.
        V::lang($config['lang']);
        
        $this->validate = $config['rules'];
        
        $this->uploadPath = $config['upload_path'];                
        if (array_key_exists('singleton_data', $config)){ 
            $this->singletonData = $config['singleton_data'];
            $this->initializeSingletonData();
        }
        if (array_key_exists('auth', $config)){ 
            $this->authData = $config['auth'];
        }
        
    }
    
    /*
     * Create tables that only contain one row if needed.
     */    
    function initializeSingletonData(){
        foreach($this->singletonData as $tableName => $tableData){
            // check if table created or not            
            // if it existed, we leave.
            if (R::findOne($tableName)){
                continue;
            }
            
            $instance = R::dispense($tableName);
            $instance->import($tableData);
            R::store($instance);
        }
    }
    
    // Fetch the singleton data from table.
    function readSingleton($tableName){
        if (!$tableName) throw new OutlawNoTableName();
        $instance = R::findOne($tableName);
        return $instance;
    }

    // Update the singleton data into table
    function updateSingleton($tableName){
        $instance = R::findOne($tableName);
        return $this->update($tableName, $instance->id);        
    }

    /*
     * A very dangerous method which inserting data into database.
     * 
     */
    function create($table_name=null){
        if (!$table_name) throw new OutlawNoTableName();

        $instance = R::dispense($table_name);

        foreach($_REQUEST as $key => $value){
            if (strpos($key, 'ol_')===0){
                $attr_name = substr($key, 3);
                // This is used for one-to-many relationship.
                // Assign the parent for the instance.
                if (strpos($attr_name, 'belong_')===0){
                    $parent_name = substr($attr_name, 7);                  
                    $parent_instance = R::load($parent_name, $value);
                    $instance->$parent_name = $parent_instance;
                }
                // This is just table column.
                // So assign the value for it.
                else{
                    $instance->$attr_name = $value;                  
                }

            }
        }
        
        // Does $_REQUEST pass the rules?
        if (!$this->validate($table_name)){
            return false;
        }
        
        // Attach files to the instance if needed.
        $this->attachFilesTo($instance);

        $id = R::store($instance);        
        return $id;
    }
    
    /*
     * Validate the $_REQUEST.
     * Store errors in $this->errors for further using.
     * Parameters:
     *     $table_name - validate with which table name rules defined in config.php.
     * @return Boolean - Pass or not
     */    
    function validate($table_name){
        if (!array_key_exists($table_name, $this->validate)){
            return true;
        }
        $v = new Valitron\Validator($_REQUEST);
        $rules = $this->validate[$table_name];
        $v->rules($rules);
        if($v->validate()) {
            return true;
        }
        else{
            $this->errors = $v->errors();
            return false;
        }
    }
    
    function attachFilesTo(&$instance){
        foreach($_FILES as $key => $value){
            if (strpos($key, 'ol_')!==0){
                continue;
            }
            // If it's one-to-one relationship,
            // store it in the same table.
            if (!is_array($value['name'])){
                // If upload failed, skip it.
                if ($_FILES[$key]["error"] !== 0){
                    continue;
                }
                // Save the file in the path.
                $tmp_name = $_FILES[$key]["tmp_name"];
                #$token = md5_file($tmp_name);
                $name = self::sanitize($_FILES[$key]["name"]);            
                move_uploaded_file($tmp_name, $this->uploadPath . "$name");            
                // Save the file name so we could find it.
                $attr_name = substr($key, 3);
                $instance->$attr_name = $name;
            }
            else{
                $files = self::reArrayFiles($_FILES[$key]);
                foreach($files as $file){
                    // If upload failed, skip it.
                    if ($file["error"] !== 0){
                        continue;
                    }
                  
                    // Save the file in the path.
                    $tmp_name = $file["tmp_name"];
                    # $token = md5_file($tmp_name);
                    $name = self::sanitize($file['name']);
                    move_uploaded_file($tmp_name, $this->uploadPath . "$name");            
                    $attr_name = substr($key, 3);
                    $file_instance = R::dispense($attr_name);
                    $table_name = $instance->getMeta('type');
                    $file_instance->$table_name = $instance;
                    $file_instance->name = $name;
                    R::store($file_instance);
                }
            }
        }      
    }
    
    function getErrors(){
        return $this->errors;
    }
    
    // A very dangerous method which removes data from database.
    function delete($table_name, $id){
        if (!$table_name) throw new OutlawNoTableName();
        if (!$id) throw new OutlawNoId();
        $instance = R::load($table_name, $id);
        R::trash( $instance );        
    }
    
    // Fetch data from table.
    function read($table_name=null, $id=null){
        if (!$table_name) throw new OutlawNoTableName();
        if (!$id) throw new OutlawNoId();
        $instance = R::load($table_name, $id);
        return $instance;
    }

    // Update data into table
    function update($table_name=null, $id){
        if (!$table_name) throw new OutlawNoTableName();
        if (!$id) throw new OutlawNoId();

        $instance = R::load($table_name, $id);

        foreach($_REQUEST as $key => $value){
            if (strpos($key, 'ol_')===0){
                $attr_name = substr($key, 3);
                $instance->$attr_name = $value;
            }
        }

        // Does $_REQUEST pass the rules?
        if (!$this->validate($table_name)){
            return false;
        }

        // Attach files to the instance if needed.
        $this->attachFilesTo($instance);

        $id = R::store($instance);                
        return $id;
    }
    
    /*
     * Fetch all rows from the table.
     * @params String
     * @return Array of RedBean beans
     */
    function readAll($table_name=null){
        if (!$table_name) throw new OutlawNoTableName();
        return R::findAll($table_name);
    }

    /*
     * Keep the methods here for the compatiblility with v1.0
     */
    function inject($table_name=null){
        return $this->create($table_name);
    }
    function take($table_name=null, $id=null){
        return $this->read($table_name, $id);
    }
    function pollute($table_name=null, $id){
        return $this->update($table_name, $id);
    }
    function murder($table_name, $id){
        return $this->delete($table_name, $id);
    }
    function gather($table_name=null){
        return $this->readAll($table_name);
    }
    
    /*
     * A simple way to protect pages from unauthorized users.
     */
    function protect(){
        session_start();
        if (isset($_SESSION['ol_logined'])){
            return true;
        }
        if ( isset($_POST['ol_user'] ) && isset( $_POST['ol_password'] )){
            if ( ($_POST['ol_user'] === $this->authData['user']) &&
                ($_POST['ol_password'] === $this->authData['password'])
            ){
                $_SESSION['ol_logined'] = true;
                return true;
            }
            echo 'Login failed.';
            exit();
        }
        echo "<form action='" . $_SERVER['PHP_SELF'] . "' method='post'>\n";
        echo "User: <br />\n";
        echo "<input type='text' name='ol_user' required /><br />\n";
        echo "Password: <br />\n";
        echo "<input type='password' name='ol_password' required /><br />\n";
        echo "<input type='submit' value='Login' />\n";
        echo "</form>\n";
        exit();
    }
    
    /*
     * Logout the user from the protection.
     */
    function logout(){
        session_start();
        unset($_SESSION['ol_logined']);
        return true;
    }
    
}
