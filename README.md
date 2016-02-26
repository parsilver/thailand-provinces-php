# ฐานข้อมูลจังหวัดในประเทศไทย(PHP)

ฐานข้อมูลนี้ถูกดัดแปลงมาจาก https://github.com/parsilver/thailand-provinces โดยการเอาข้อมูลทั้งหมดมาเป็น PHP แล้วรวบให้เป็น ORM เพื่อที่จะสามารถใช้งานได้ง่ายยิ่งขึ้น

[![Build Status](https://travis-ci.org/parsilver/thailand-provinces-php.svg?branch=master)](https://github.com/parsilver/thailand-provinces-php)

## การติดตั้ง

```sh
composer require parsilver/thailand-provinces-php
```

## เริ่มต้นการใช้งาน

ยกตัวอย่างเช่น หากท่านต้องการดึงจังหวัดทั้งหมด ให้เรียกใช้งานแค่ `Factory::province()`

```php
<?php
use PA\ProvinceTh\Factory;

$provinces = Factory::province(); // PA\ProvinceTh\Provider\ProviderCollection
echo $provinces; // Json
```

หากต้องการแปลงเป็น `Array` ก็สามารถทำได้ดังนี้
```php
<?php
$provinceArray = $provinces->toArray();
```

นอกจากนั้น หากต้องการค้นหาว่าจังหวัดนั้นๆมีอำเภอใดบ้าง
```php
<?php
$amphures = $provinces->find(1)->amphures(); // PA\ProvinceTh\Provider\ProviderCollection
echo $amphures; // Json
```


## API

#### PA\ProvinceTh\Factory
```php
<?php
use PA\ProvinceTh\Factory;

/**
* ภูมิภาค
* @return PA\ProvinceTh\Provider\Geography|PA\ProvinceTh\Provider\ProviderCollection
*/
$geography  = Factory::geography();

/**
* จังหวัด
* @return PA\ProvinceTh\Provider\Province|PA\ProvinceTh\Provider\ProviderCollection
*/
$provinces  = Factory::province();

/**
* อำเภอ
* @return PA\ProvinceTh\Provider\Amphure|PA\ProvinceTh\Provider\ProviderCollection
*/
$amphures   = Factory::amphure();

/**
* ตำบล
* @return PA\ProvinceTh\Provider\District|PA\ProvinceTh\Provider\ProviderCollection
*/
$districts  = Factory::district();
```

#### PA\ProvinceTh\Provider\ProviderCollection

```php
<?php
use PA\ProvinceTh\Factory;

/**
* @return PA\ProvinceTh\Provider\ProviderCollection
*/
$provinces = Factory::province();

/**
* จำนวน
* @return int
*/
$provinces->count();

/**
* ค้นหาจาก Primary key และ return 1 column
* @return PA\ProvinceTh\Provider\ProviderCollection
*/
$provinces->find($id);

/**
* ค้นหา
* @return PA\ProvinceTh\Provider\ProviderCollection
*/
$provinces->where($key, $value);

/**
* Foreach
*@return Void
*/
$provinces->each(function($value, $key){
    // หาต้องการหยุด ให้ return false
});

/**
* ค้นหาด้วยตัวเอง
* @return PA\ProvinceTh\Provider\ProviderCollection
*/
$provinces->filter(function($value, $key){
    return true; // Return true หากค้นพบ
});

/**
* @return array
*/
$provinces->toArray();

/**
* Get primary key
* @return string
*/
$provinces->getPrimaryKey()
```

#### `PA\ProvinceTh\Provider\Geography`

```php
<?php
use PA\ProvinceTh\Factory;

$geography = Factory::geography();

/**
* จังหวัดของภูมิภาคนั้น
* @return PA\ProvinceTh\Provider\Province|PA\ProvinceTh\Provider\ProviderCollection
*/
$geography->find(1)->provinces();
```

#### `PA\ProvinceTh\Provider\Province`

```php
<?php
use PA\ProvinceTh\Factory;

$province = Factory::province();

/**
* ภูมิภาคของจังหวัดนั้น
* @return PA\ProvinceTh\Provider\Geography|PA\ProvinceTh\Provider\ProviderCollection
*/
$province->find(1)->geography();

/**
* อำเภอทั้งหมดคของจังหวัดนั้น
* @return PA\ProvinceTh\Provider\Amphure|PA\ProvinceTh\Provider\ProviderCollection
*/
$province->find(1)->amphures();
```

#### `PA\ProvinceTh\Provider\Amphure`

```php
<?php
use PA\ProvinceTh\Factory;

$amphure = Factory::amphure();

/**
* จังหวัดคของอำเภอนั้น
* @return PA\ProvinceTh\Provider\Province|PA\ProvinceTh\Provider\ProviderCollection
*/
$amphure->find(1)->province();

/**
* ตำบลทั้งหมดของอำเภอนั้น
* @return PA\ProvinceTh\Provider\District|PA\ProvinceTh\Provider\ProviderCollection
*/
$amphure->find(1)->districts();
```

#### `PA\ProvinceTh\Provider\District`

```php
<?php
use PA\ProvinceTh\Factory;

$district = Factory::district();

/**
* อำเภอของตำบลนั้น
* @return PA\ProvinceTh\Provider\Amphure|PA\ProvinceTh\Provider\ProviderCollection
*/
$district->find(1)->amphure();
```
