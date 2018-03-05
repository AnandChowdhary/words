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

An example post looks like then when saved:

```
{
    "title": "TmZRWXZZVDNBM21LWWJ1WW9Fd1FzZz09",
    "date": "2018-03-05 11:33:20",
    "body": "R2d5SkkrTTJrVWlCWjZubkU4T3VvTElPRU1mdzNkM1cySFJGTmNJTTlGWT0="
}
```

## REST API

All requests (except when generating a new token) need to be authenticated with a `token` header.

### `POST /` to get a new token

Request body:

```
{
	"password": "example_password"
}
```

Response body:

```
{
    "api": "words",
    "version": "4.1",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHBpcmVzIjoiMjAxOC0wMy0wNiAxMjoyOTo1MiJ9.Coe969vqWQDmd34G04Y5HxOhOaz5citBOr5yEjxI6j0",
    "expires": "2018-03-06 12:29:52"
}
```

### `GET /posts` to get a list of all posts

Response body:

```
{
    "api": "words",
    "version": "4.1",
    "posts": [
        {
            "id": "20180305104246d5bfde2cdc.json",
            "title": "Post title has been edited",
            "date": "2018-03-05 11:26:44"
        },
        {
            "id": "20180305113204b375272648.json",
            "title": "Post title",
            "date": "2018-03-05 11:32:04"
        }
    ]
}
```

### `PUT /posts` to create a new post

Request body:

```
{
	"title": "Post title",
	"body": "<p>This is an example post!</p>"
}
```

Response body:

```
{
    "api": "words",
    "version": "4.1",
    "created": true
}
```

### `GET /post/{id}` to get a specific post

Response body:

```
{
    "api": "words",
    "version": "4.1",
    "post": {
        "title": "Post title has been edited",
        "date": "2018-03-05 11:26:44",
        "body": "<p>This is an example post!</p>"
    }
}
```

### `DELETE /post/{id}` to delete a specific post

Response body:

```
{
    "api": "words",
    "version": "4.1",
    "deleted": true
}
```

### `PUT /post/{id}` to update a specific post

Request body:

```
{
	"title": "Updated post title",
	"body": "<p>This is an example post which has been updated!</p>"
}
```

Response body:

```
{
    "api": "words",
    "version": "4.1",
    "updated": true
}
```
