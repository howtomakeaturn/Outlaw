<?php

require_once(realpath(dirname(__FILE__)) . '/src/rb.php');
require_once(__DIR__ . '/src/Valitron/Validator.php');
require_once(__DIR__ . '/src/Exceptions.php');
use Valitron\Validator as V;
class Outlaw{
    
    protected $validate;
    protected $errors;
    protected $uploadPath;

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

        R::setup($config['database']['dns'], $config['database']['user'], $config['database']['password']);    
        
        V::langDir(__DIR__.'/src/Valitron/lang'); // always set langDir before lang.
        V::lang($config['lang']);
        
        $this->validate = $config['rules'];
        
        $this->uploadPath = $config['upload_path'];                
    }

    /*
     * A very dangerous method which inserting data into database.
     * 
     */
    function inject($table_name=null){
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
    function murder($table_name, $id){
        if (!$table_name) throw new OutlawNoTableName();
        if (!$id) throw new OutlawNoId();
        $instance = R::load($table_name, $id);
        R::trash( $instance );        
    }
    
    // Fetch data from table.
    function take($table_name=null, $id=null){
        if (!$table_name) throw new OutlawNoTableName();
        if (!$id) throw new OutlawNoId();
        $instance = R::load($table_name, $id);
        return $instance;
    }

    // Update data into table
    function pollute($table_name=null, $id){
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
    function gather($table_name=null){
        if (!$table_name) throw new OutlawNoTableName();
        return R::findAll($table_name);
    }
    
}
