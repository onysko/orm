<?php
/**
 * Created by PhpStorm.
 * User: VITALYIEGOROV
 * Date: 30.11.15
 * Time: 16:38
 */
namespace samsonframework\orm;

/**
 * Database entity manager.
 * @package samsonframework\orm
 */
class Manager extends Database implements ManagerInterface
{
    /** @var string Entity identifier */
    protected $entityName;

    /** @var string Entity primary field name */
    protected $primaryFieldName;

    /** @var array Collection of entity field names and their types */
    protected $attributes = array();

    /** @var \PDO Database driver */
    protected $driver;

    /**
     * Manager constructor.
     *
     * @param string $entityName Entity name
     * @param array $attributes Key-value collection with field name => type
     * @param \PDO $driver database low-level driver
     */
    public function __construct($entityName, $attributes, $driver)
    {
        $this->entityName = $entityName;
        $this->attributes = $attributes;
        $this->driver = $driver;
    }

    public function execute($sql)
    {
        $result = array();

        if (isset($this->driver)) {
            // Store timestamp
            $tsLast = microtime(true);

            try {
                // Perform database query
                $result = $this->driver->prepare($sql)->execute();
            } catch (\PDOException $e) {
                echo("\n" . $sql . '-' . $e->getMessage());
            }

            // Store queries count
            $this->count++;

            // Count elapsed time
            $this->elapsed += microtime(true) - $tsLast;
        }

        return $result;
    }

    /**
     * Get query for database entity to work with.
     *
     * @return QueryInterface Query for building database request
     */
    public function query()
    {
        return new Query($this->entityName, $this);
    }

    /**
     * Get new entity instance.
     *
     * @return RecordInterface New database manager entity instance
     */
    public function instance()
    {
        return new $this->entityName($this);
    }

    /**
     * Create new database entity record.
     * @param RecordInterface $entity Entity record for creation
     * @return RecordInterface Created database entity record with new primary identifier
     */
    public function create(RecordInterface $entity)
    {
        $fields = $this->getFields($entity);

        $this->execute('INSERT INTO `' . $this->entityName . '` (`'
            . implode('`,`', array_keys($fields)) . '`) VALUES (' . implode(',', $fields) . ')'
        );
    }

    /**
     * Read database entity records from QueryInterface.
     *
     * @param QueryInterface $query For retrieving records
     * @return RecordInterface[] Collection of read database entity records
     */
    public function read(QueryInterface $query)
    {
        // TODO: Implement read() method.
    }

    /**
     * Update database entity record.
     *
     * @param RecordInterface $entity Entity record for updating
     */
    public function update(RecordInterface $entity)
    {
        // Generate entity fields update command
        $fields = array();
        foreach ($this->getFields($entity) as $fieldName => $fieldValue) {
            $fields[] = '`'.$this->entityName.'`.`'.$fieldName.'` = "'.$fieldValue.'"';
        }

        $this->execute('UPDATE `' . $this->entityName . '` SET '
            . implode(',', $fields)
            . ' WHERE `' . $this->entityName . '`.`' . $this->primaryFieldName . '`="'
            . $this->quote($entity->id) . '"');
    }

    /**
     * Delete database record from database.
     *
     * @param RecordInterface $entity Entity record for removing
     */
    public function delete(RecordInterface $entity)
    {
        $this->execute('DELETE FROM `' . $this->entityName . '` WHERE '
            . $this->primaryFieldName . ' = "' . $this->quote($entity->id) . '"'
        );
    }

    /**
     * Convert RecordInterface instance to collection of its field name => value.
     *
     * @param RecordInterface $object Database record instance to convert
     * @return array Collection of key => value with SQL fields statements
     */
    protected function &getFields(RecordInterface &$object = null)
    {
        $collection = array();
        foreach ($this->attributes as $attribute => $type) {
            if ($type == 'timestamp') {
                continue;
            } elseif ($this->primaryFieldName == $attribute) {
                continue;
            }

            $collection[$attribute] = $this->quote($object->$attribute);
        }

        return $collection;
    }

    /**
     * Quote variable for security reasons.
     *
     * @param string $value
     * @return string Quoted value
     */
    protected function quote($value)
    {
        return $this->driver->quote($value);
    }
}
