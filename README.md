# OmniFetch for Phalcon
> OmniFetch for Phalcon is useful library that would suit most of your fetching API endpoint needs. It helps you in fetching row(s) of data based on a particular model and attach data from other related models easily. It also allows for complex filter conditions using combinations of **AND** and **OR** Boolean operators and the use of **LIKE**, **IS** and **IS NOT** comparison operators with pagination. 

[![Total Downloads](https://img.shields.io/packagist/dt/aros/omnifetch-phalcon.svg?style=flat-square)](https://packagist.org/packages/aros/omnifetch-phalcon)

## Dependencies
* Installed Phalcon <= 2.1.0 
* For the library to work properly, the relationships between Models need to be set up fully.

## Installation 
The preferred way to install this extension is through [Composer](https://getcomposer.org)

``` bash
$ composer require aros/omnifetch-phalcon
```

Another alternative is to simply add the following line to the require block of your `composer.json` file.

```
"aros/omnifetch-phalcon": "dev-master"
```

Then run `composer install` or `composer update` to download it and have the autoloader updated.

## Public Methods
* **getAll**(string **$main_model**, array **$params**, array **$settings**)

* **getOne**(string **$main_model**, array **$params**, array **$settings**)

## Parameters
These are the valid keys allowed in **$params** (which is an input parameter in the public methods). This is the  The available ones are as follows:

* **embeds** - This is an array containing the alias of the main model's relations which is to be attached to the main model's data in the result.
* **filters** - This is an array containing filtering conditions using in fetching data. The conditions are handled and combined in the order they are in the array. Each condition is array in itself which contain the following:
    * **field** - This indicates the column to be used for the filtering. **Please note:** The model's alias **must** be used in the name of this column e.g. `Card.id`. The field using here can also be that of a related model to the main model. For example, if `Issuer` is a related model to `Card`, `Issuer.name` can be used to filter.
    * **value** - This indicates the value of the field to be used for the filtering. 
    * **cmp** (optional) - This represents the comparison operator to be used. By default, it is `=`. This can be `>`, `<`, `=`, `>=`, `<=`, `!=`, `LIKE`, `IS` and `IS_NOT`. 
    * **cond** (optional) - This represents the Boolean operator to be used. By default, it is `AND` but `OR` is also supported
* **page** - This is an integer indicating the page to be fetched. It is only applicable to the `getAll` method. It is defaulted to `0` when omitted, which indicates the first page.
* **page_size** - This is an integer indicating the maximum size of the current page. It is defaulted to `20` when omitted. It is only applicable to the `getAll` method.
* **order_by** - This is string representing the order clause in sql. **Please note:** The model's alias should be used in the name of the column used for the ordering. For example, use `Card.id` rather than `id` to avoid column ambiguity especially when using embeds.
 
## Settings
These are the valid keys allowed in **$settings** (which is an input parameter in the public methods). This is the  The available one(s) are as follows:
* **primary_key** - It is indicates the primary key of the `main model`.  This is **compulsory**. 

## Output parameters
For the `getAll` method, the following are the parameters outputed:
* **list** - contains the list of results
* **pagination** - contains information of the paginations
    * **next** - the next page index, it is `null` if there is no next page
    * **previous** - the previous page index, it is `null` if there is no previous page
    * **total_count** - Total count of all the records fit the filtering conditions
    * **count** - the number of records in the current page

For the `getOne` method, the output is a single record which is an array or a `null` if the record does not exist.

## Sample usage
First the relationship has to be set up in the Model:

```php
<?php

class Book extends \Phalcon\Mvc\Model
{
    //...
    public function initialize()
    {
        $this->belongsTo('type_id', 'BookType', 'id', array('alias' => 'BookType'));
        $this->belongsTo('author_id', 'Author', 'id', array('alias' => 'Author'));
    }
    //...
}
```
The following is a sample usage of `getOne` method:
```php
<?php
use \OmniFetch\PhalconOmniFetch;

class BookController extends ControllerBase
{
    public function getOneAction()
    {
        $filters = $this->request->getQuery('filters');
        // filters = [{"field": "Book.id", "value": "230"}]
        $embeds = $this->request->getQuery('embeds');
        // embeds = ["BookType", "Author"]

        $omni_fetch = new PhalconOmniFetch();
        $result = $omni_fetch->getOne('Book', [
            'embeds' => json_decode($embeds),
            'filters' => json_decode($filters, true)
        ],[
            'primary_key' => 'id'
        ]);

        return $this->response->sendSuccess($result);
    }
}
```
The result would be:
```json
{
    "id": 230,
    "author_id": 1,
    "type_id": 1,
    "name": "Wizard of Oz",
    "code": "CB128EF2",
    "created_date": "2015-01-02 14:23:12",
    "Author": {
        "id": 1,
        "name": "Jon Doe",
        "address": "Ikeja, Lagos State, Nigeria"
    },
    "BookType": {
        "id": 1,
        "name": "fantasy"
    }
}
```

The following is a sample usage of `getAll` method:
```php
<?php
use \OmniFetch\PhalconOmniFetch;

class CardController extends ControllerBase
{
    public function getAllAction()
    {
        $filters = $this->request->getQuery('filters');
        // filters = [{"field": "Book.code", "value": "%25CB%25", "cmp": "LIKE"}]
        // [Note: %25 is the url encoding for %]
        $embeds = $this->request->getQuery('embeds');
        // embeds = ["BookType", "Author"]
        $order_by = $this->request->getQuery('order_by');
        // order_by = Book.id DESC

        $omni_fetch = new PhalconOmniFetch();
        $result = $omni_fetch->getAll('Book', [
            'embeds' => json_decode($embeds),
            'filters' => json_decode($filters, true),
            'order_by' => $order_by,
            'page_size' => 3 //made it 3 for example sake
        ],[
            'primary_key' => 'id'
        ]);

        return $this->response->sendSuccess($result);
    }
}
```

The result would be:
```json
{
    "list" : [
        {
            "id": 230,
            "author_id": 1,
            "type_id": 1,
            "name": "Wizard of Oz",
            "code": "CB128EF2",
            "created_date": "2015-01-02 14:23:12",
            "Author": {
                "id": 1,
                "name": "Jon Doe",
                "address": "Ikeja, Lagos State, Nigeria"
            },
            "BookType": {
                "id": 1,
                "name": "fantasy"
            }
        },
        {
            "id": 127,
            "author_id": 2,
            "type_id": 2,
            "name": "Love once again",
            "code": "987ACB43",
            "created_date": "2015-03-04 03:50:34",
            "Author": {
                "id": 2,
                "name": "Mary Porter",
                "address": "Abeokuta, Ogun State, Nigeria"
            },
            "BookType": {
                "id": 2,
                "name": "romance"
            }
        },
        {
            "id": 59,
            "author_id": 1,
            "type_id": 5,
            "name": "The life of Mine",
            "code": "873FCB12",
            "created_date": "2014-05-12 20:01:59",
            "Author": {
                "id": 1,
                "name": "Jon Doe",
                "address": "Ikeja, Lagos State, Nigeria"
            },
            "BookType": {
                "id": 5,
                "name": "biography"
            }
        }
    ],
    "pagination": {
        "next": 1,
        "previous": null,
        "total_count": "6",
        "count": 3
    }
}
```

## Todo
* Add support for MANY\_TO\_MANY relations for the embeds
* Add support for composite primary keys