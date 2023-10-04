# Simple high entropy random data generator

This small library generates high entropy random data from /dev/urandom

Aimed to UNIX based systems but has fallbacks for other systems

## Formats

Generate data in these formats:

- raw
- integer
- hexadecimal

## Requirements

php >= 5.4

## Installation

Preferable use composer

```sh
composer require pedrosancao/php-random-data
```

## Usage

```php
$bytes = \PedroSancao\Random::raw($length);
$int = \PedroSancao\Random::int($length);
$hex = \PedroSancao\Random::hex($length);
```

## To do list

- add new types (text, dummy base64, etc.)

## Licence

MIT, see LICENCE.

## Recommended reading

- [Insufficient Entropy For Random Values](http://phpsecurity.readthedocs.org/en/latest/Insufficient-Entropy-For-Random-Values.html)
