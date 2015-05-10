<?php
/**
 * Created by PhpStorm.
 * User: egorov
 * Date: 09.05.2015
 * Time: 13:05
 */
namespace samsonframework\orm;

/**
 * Class Database
 * @package samsonframework\orm
 */
class Database implements DatabaseInterface
{
    /** @var resource Database driver */
    protected $driver;

    /** @var string Database name  */
    protected $database;

    /** @var int Amount of miliseconds spent on queries */
    protected $elapsed;

    /** @var int Amount queries executed */
    protected $count;


    /** {@inheritdoc} */
    public function connect(
        $database,
        $username,
        $password,
        $host = 'localhost',
        $port = 3306,
        $driver = 'mysql',
        $charset = 'utf8'
    ) {
        // If we have not connected yet
        if (!isset($this->driver)) {

            // Create connection string
            $dsn = $driver . ':host=' . $host . ';dbname=' . $database . ';charset=' . $charset;

            $this->database = $database;

            // Set options
            $opt = array(
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            );

            try { // Connect to a database
                $this->driver = new \PDO($dsn, $username, $password, $opt);
            } catch (\PDOException $e) {
                // Handle exception
            }
        }
    }

    /** {@inheritdoc} */
    public function & query($sql)
    {
        $result = array();

        if (isset($this->driver)) {
            // Store timestamp
            $tsLast = microtime(true);

            try {
                // Perform database query
                $result = $this->driver->query($sql)->fetchAll();
            } catch(\PDOException $e) {
                trace($sql.'-'.$e->getMessage());
            }

            // Store queries count
            $this->count++;

            // Отметим затраченное время на выполнение запроса
            $this->elapsed += microtime(true) - $tsLast;
        }

        return $result;
    }

    /** @deprecated Use query() */
    public function & simple_query($sql)
    {
        $result = array();

        if (isset($this->driver)) {
            // Store timestamp
            $tsLast = microtime(true);

            try {
                // Perform database query
                $result = $this->driver->query($sql)->execute();
            } catch(\PDOException $e) {
                trace($sql.'-'.$e->getMessage());
            }


            // Store queries count
            $this->count++;

            // Отметим затраченное время на выполнение запроса
            $this->elapsed += microtime(true) - $tsLast;
        }

        return $result;
    }

    public function create($className, & $object = null)
    {
        // Get all database table characteristics
        extract($this->__get_table_data($className));

        // ??
        $fields = $this->getQueryFields($className, $object);

        // Build SQL query
        $sql = 'INSERT INTO `' . $_table_name . '` (`' . implode('`,`', array_keys($fields)) . '`) VALUES (' . implode(',', $fields) . ')';

        $this->query($sql);

        // Return last inserted row identifier
        return $this->driver->lastInsertId();
    }

    public function update($className, & $object)
    {
        // Get all database table characteristics
        extract($this->__get_table_data($className));

        // ??
        $fields = $this->getQueryFields($className, $object, true);

        // Build SQL query
        $sql = 'UPDATE `' . $_table_name . '` SET ' . implode(',',
                $fields) . ' WHERE ' . $_table_name . '.' . $_primary . '="' . $object->id . '"';

        $this->query($sql);
    }

    public function delete($className, & $object)
    {
        // Get all database table characteristics
        extract($this->__get_table_data($className));

        // Build SQL query
        $sql = 'DELETE FROM `' . $_table_name . '` WHERE ' . $_primary . ' = "' . $object->id . '"';

        $this->query($sql);
    }

    /** Count query result */
    public function count($className, $query)
    {
        // Get SQL
        $sql = 'SELECT Count(*) as __Count FROM (' . $this->prepareSQL($className, $query) . ') as __table';

        // Выполним запрос к БД
        $result = $this->query($sql);

        return $result[0]['__Count'];
    }

    /**
     * Special accelerated function to retrieve db record fields instead of objects
     *
     * @param string $className
     * @param dbQuery $query
     * @param string $field
     *
     * @return array
     */
    public function & findFields($className, $query, $field)
    {
        // WTF?
        $result = array();
        if ($query->empty) {
            return $result;
        }

        // Get SQL
        $sql = $this->prepareSQL($className, $query);

        // Get all database table characteristics
        extract($this->__get_table_data($className));

        // Get table column index by its name
        $columnIndex = array_search($field, array_values($_table_attributes));

        $result = $this->driver->query($sql)->fetchAll(\PDO::FETCH_COLUMN, $columnIndex);

        // Вернем коллекцию полученных объектов
        return $result;
    }

    /**
     * Выполнить защиту значения поля для его безопасного использования в запросах
     *
     * @param string $value Значения поля для запроса
     * @return string $value Безопасное представление значения поля для запроса
     */
    protected function protectQueryValue($value)
    {
        // If magic quotes are on - remove slashes
        if (get_magic_quotes_gpc()) {
            $value = stripslashes($value);
        }

        // Normally escape string
        $value = $this->driver->quote($value);

        // Return value in quotes
        return $value;
    }

    /** Destructor */
    public function __destruct()
    {
        try {
            unset($this->driver);
        } catch (Exception $e) {
            // Handle disconnection error
        }
    }
}
