# Words

Words is a platform to _just_ write. It doesn't come in the way of you and your thoughts, with complete data privacy.

## How it works

This repo contains a REST CRUD API for the backend of Words, a platform I made to write and share words with my significant other.

It uses the crypto library [OpenSSL](http://php.net/manual/en/intro.openssl.php) to securely encrypt every word you write, with token-based authentication using [JWT](https://github.com/firebase/php-jwt).

It's database-free, since it uses a JSON file structure to store everything, so you can easily make file backups, import and export data, and manage it completely without any privacy breaches.

## Structure

Words uses a single file, `meta.json` in the root for all your settings. This file contains the directory location to save and view all posts from, as well as your root password, OpenSSL key and initialization vector.

It looks something like this:

```
{
	"files": "./words/",
	"key": "example_secure_key",
	"iv": "example_initialization_vector",
	"password": "$2y$10$wc5FaC/hnNipOAMHLh4yxuaYFBm0wSa1mE07mH187JSDCumtujfk6"
}
```

The `password` in this file is an output of `password_hash("example_password", PASSWORD_DEFAULT)`. You should use the same function to generate a hash of your password and store it in this file.