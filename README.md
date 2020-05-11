# laravel-model-creator

Create more maintainable eloquent models from cli, migrations and json files.

Use laravel-model-creator to generate a more maintainable model class automaticly.

### start

Install laravel-model-creator using composer in your laravel project:

```SHELL
$ composer require ytlmike/laravel-model-creator
```

use `create:model` command to create a model class:
```SHELL
$ php artisan create:model

 Class name of the model to create:
 > user

Class \App\Models\User created successfully.

 New field name (press <return> to stop adding fields):
 > name

 Field type::
  [0] int
  [1] tinyint
  [2] varchar
  [3] datetime
 > 2

 Field display length [255]:
 > 45

 Can this field be null in the database (nullable) (yes/no) [no]:
 > 

 Default value of this field in tht database []:
 > 

 Comment of this field in the database []:
 > user name

 Add another property? Enter the property name (or press <return> to stop adding fields):
 > 
```
The generated model class:
```PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    public function getName()
    {
        return $this->name;
    }
    
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }
}
```

You can use --const option to generate field constants：

```SHELL
$ php artisan create:model --const
```
The generated model class:

```PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    /**
     * user name
     * @Column (type='varchar', length=45, not null)
     */
    const FIELD_NAME = 'name';
    
    public function getName()
    {
        return $this->getAttribute(self::FIELD_NAME);
    }
    
    public function setName($name)
    {
        $this->setAttribute(self::FIELD_NAME, $name);
        return $this;
    }
}
```
