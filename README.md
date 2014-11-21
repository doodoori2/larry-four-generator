# Larry Four - The Laravel 4 Model & Migration Generator

This project is forked from <a href="https://github.com/XCMer/larry-laravel-generator">Larry</a>.

implement just one method for generate models from existing tables. Use it as follows:

larry:fromdbwithmodel

    php artisan larry:fromdbwithmodel

The tables that Larry processes can be altered by specifying the `only` and `except` options to the command.

**Below are certain points to note:**

`larry:fromdbwithmodel` just make skeleton model files. The method do not convert relationship data. (yet)

If same model name exists in /app/models directory, the file will be overwritten. 

The other commands are same with <a href="https://github.com/XCMer/larry-laravel-generator">Larry</a>.

