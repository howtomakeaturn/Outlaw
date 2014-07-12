# Outlaw

A PHP library helping implement CRUD in a dirty and fast way.

## The Outlaw says:
> Sometimes after you implemented html and css, you hope your application is finished.
> Unfortunately, you still need to make migrations, build models, pass parameters in controller, and etc.
> I help solving this in a dirty and fast way.

## Goals
* Enable developers to define the database schema in HTML.
* No need to make migrations. The Outlaw builds the table automatically.
* No need to pass parameters in controller. The Outlaw use $_GET, $_POST, and $_REQUEST directly.
* No need to implement models. The Outlaw provides basic CRUD for you.

## Rules
* Prefix every html input fields name the Outlaw needed with 'ol_'
* You only need to pass the table name and primary key in the controller if needed, all the other parameters the outlaw will handle.

## Warning
This library should **only** be used for **insensitive applications** or **prototyping new ideas**.
It's very fast but really dirty.
Use it carefully.

## Getting Started

Let's say, you need a blogging system.

In config file, set database name and password.
```php
// config.php

$config['database'] = array(
    'dsn' => 'mysql:host=localhost;dbname=koala',
    'user' => 'koala',
    'password' => 'koala'
);
```

### Create

First we need a place to create articles.
We need two fields 'title' and 'content'.

```html
<form action='/blog/create' method='post'>
    Title: <input type='text' name='ol_title' />
    Content: <input type='text' name='ol_content' />
    <input type='submit' value='SEND' />
</form>
```

And then tell the outlaw where to **create** the data in the blog controller.
```php
function __construct(){
    $this->ol = new Outlaw();
}

public function create()
{
    $this->ol->create('articles');
}    
```
Now check your database, the 'articles' table is created, and you just inserted one row into it!

### Read

We also need a place to see all the articles.
Let's **readAll** them first.
```php
public function index(){
    $this->data['articles'] = $this->ol->readAll('articles');
    $this->template->build('product/index', $this->data);
}
```

So you can use them.
```php
<?php foreach($articles as $a): ?>
    <?php echo $a->title ?>
    <?php echo $a->content ?>
<?php endforeach; ?>
```
To view a single article, tell the outlaw the table name and id to **read** it.
```php
function view($id){
    $this->data['article'] = $this->ol->read('articles', $id);
    $this->template->build('demo/view', $this->data);        
}
```

### Update

To edit an article, tell the outlaw the table name and id to **update** it.
```php
function update(){
    $id = $_POST['ol_id'];
    $this->ol->update('articles', $id))
    redirect('/blog/view/' . $id);
}
```

### Delete

To delete an article, tell the outlaw the table name and id so outlaw can know who to **delete**.
```php
function delete(){
    $id = $_REQUEST['id'];
    $this->ol->delete('articles', $id);
    redirect('/blog');
}
```

### Upload File
Set the upload path in config.php.
```php
$config['upload_path'] = './upload/';             
        
```

Then in the html:

```html
<label>Person</label>
<input type="file" name='ol_person'>

<label>Logo</label>
<input type="file" name='ol_logo'>
```

Outlaw will rename the files, store them in the path, and save the file name in attributes after **create**.

For instance, we could show the above files like this:

```php
    <img src='/upload/<?php echo $article->person ?>' />    
    <img src='/upload/<?php echo $article->logo ?>' />    
```

## Advanced Topics

### Validation
Validating with Valitron:

https://github.com/vlucas/valitron

```php
// Set all the fields and table in config file.
$config['rules'] = array(
    'articles' => array(
        // notice the attribute is wrapped in an array even it's just a string
        'required' => [['ol_title'], ['ol_content']],
        'lengthMin' => [['ol_title', 5], ['ol_content', 10]]
    ),
    'stores' => [
        'required' => [ ['ol_name'], ['ol_boss'], ['ol_phone'], ['ol_address'] ]
    ]
);
```
It utilize in **create** and **update** method.
If fail to pass validation, they return false. 
And you can get validation error message by getErrors methd.
```php
      if ($this->ol->update('articles', $id)){
          redirect('/demo/view/' . $id);
      }
      else{
          exit(var_export($this->ol->getErrors()));
      }

```

### One-to-many Relationship
Let's say you want to assign an author for the article.
The user has an id value of '5' in 'users' table.
```html
<form action='/blog/create' method='post'>
    <input type='hidden' name='ol_belong_users' value='5'>
    Title: <input type='text' name='ol_title' />
    Content: <input type='text' name='ol_content' />
    <input type='submit' value='SEND' />
</form>
```
* prefix with ol_belong_
* followed by the table name
* set value as the id of the parent

Then you can utilize the relationship as this(notice the **ownArticles** attributes created by the outlaw):
```php
// child to parent
// Notice it's defined by the table name. 
// Although you think 'user' is better than 'users'.
echo $article->users->name;

// parent to children
$user = $ol->read('users', '5');
foreach ($user->ownArticles as $article){
    echo $article->title;
}
```

### Upload Multiple Files

Add 'multiple' attribute and '[]' to the name:

```html
<label>Photos</label>
<input type="file" name='ol_photos[]' multiple>
```
Because it's no longer one-to-one relationship, so we cannot use the same table.
Outlaw will create a 'photos' table, which only contains 'id', 'name', and 'articles_id' as foreign key.

And the usage becomes:
```php
<?php foreach($article->ownPhotos as $photo): ?>
    <img src='/upload/<?php echo $photo->name ?>' />    
<?php endforeach; ?>
```


## Reserved Words in html input name
* ol_belong_*

## Design Principle
* Type as less characters as possible.

## API
* create ($table_name)

> Insert other $_REQUEST parameters prefixed with 'ol_' into $table_name.

* read ($table_name, $id)

> Fetch one single row.

* update ($table_name, $id)

> Update one single row.

* delete ($table_name, $id)

> Delete one row.

* readAll ($table_name)

> Get all the rows.

* getErrors()

> Get error messages about validation failure.

## Technical Detail
* Manipulate the database with Redbeanphp 3.5

http://redbeanphp.com/manual3_0/quick_tour

That's why the Outlaw doesn't need migrations or existing tables.

* Validating with Valitron 1.1.7

https://github.com/vlucas/valitron

* Using $_REQUEST array in PHP directly, so you can pass variable either by GET or POST or even COOKIE.

## Todo

* Many-to-many relationship

* More security maybe ...

* Remove the files if it's overwritten
