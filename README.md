# SQLBuilder
Classes for generate SQL queries (select/insert/update/delete) on different platforms (MySQL, MS SQL)

### For run tests:
`./vendor/bin/phpunit`

### For create documentation:
`./vendor/bin/phpdoc -d ./src -t ./docs/`

###To Do section
* Table transfer class from MySQL to MSSQL and from MS to My
* Transfer from CSV and from MS to MS or My to My

###Purposes
1) Provide tools for automatic copy/move tables from one db/engine to another if there are no access to mysql/mysqldump 