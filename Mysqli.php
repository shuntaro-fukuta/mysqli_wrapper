<?php

class Mysqli
{
    private $mysqli;
    private $bind_types = [
        'string'  => 's',
        'integer' => 'i',
        'double'  => 'd',
        'NULL'    => 's',
    ];

    public function __construct(string $host, string $username, string $password, string $db_name, string $charset)
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $this->mysqli = new mysqli($host, $username, $password, $db_name);
        $this->mysqli->set_charset($charset);
    }

    public function fetchOne(string $table_name, array $columns, array $where): array
    {
        $options = [];

        $options['where'] = $where;
        $options['limit'] = 1;

        $row = $this->fetch($table_name, $columns, $options);
        if ($row === []) {
            return [];
        }

        return $row[0];
    }

    public function fetch(string $table_name, array $columns, array $options): array
    {
        $columns = implode(',', $columns);
        $query   = "SELECT {$columns} FROM {$table_name}";

        $has_where_option = false;
        if (!empty($options)) {
            if (isset($options['where']) && !is_null($options['where'])) {
                $has_where_option = true;

                $query .= $this->getWhereQuery($options['where']);
            }

            if (isset($options['order_by'])) {
                $query .= " ORDER BY {$options['order_by']}";
            }

            if (isset($options['limit'])) {
                $query .= ' LIMIT ' . (int) $options['limit'];

                if (isset($options['offset'])) {
                    $query .= ' OFFSET ' . (int) $options['offset'];
                }
            }
        }

        $stmt = $this->mysqli->prepare($query);
        if ($has_where_option) {
            $stmt = $this->bindParams($stmt, $this->getWhereValues($options['where']));
        }

        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function count(string $table_name, array $where = null): int
    {
        $query = "SELECT COUNT(*) FROM {$table_name}";

        if (is_null($where)) {
            $results = $this->mysqli->query($query);
        } else {
            $query       .= $this->getWhereQuery($where);
            $where_values = $this->getWhereValues($where);

            $stmt = $this->mysqli->prepare($query);
            $stmt = $this->bindParams($stmt, $where_values);

            $results = $stmt->get_result();
        }

        return (int) $results->fetch_assoc()['COUNT(*)'];
    }

    public function insert(string $table_name, array $values): void
    {
        $columns = array_keys($values);
        $values  = array_values($values);

        $columns = implode(', ', $columns);

        $place_holders = array_fill(0, count($values), '?');
        $place_holders = implode(', ', $values);

        $query = "INSERT INTO {$table_name} ({$columns}) VALUES ({$values})" ;

        $stmt = $this->mysqli->prepare($query);
        $stmt = $this->bindParams($stmt, $values);
        $stmt->execute();
    }

    public function update(string $table_name, array $values, ?array $where): void
    {
        $columns = array_keys($values);
        $values  = array_values($values);

        $place_holders = [];
        foreach ($columns as $column) {
            $place_holders[] = "{$column} = ?";
        }
        $place_holders = implode(', ', $place_holders);

        $query = "UPDATE {$table_name} SET {$place_holders}";

        if ($where !== null) {
            $query       .= $this->getWhereQuery($where);
            $where_values = $this->getWhereValues($where);
            array_merge($values, $where_values);
        }

        $stmt = $this->mysqli->prepare($query);
        $stmt = $this->bindParams($stmt, $values);
        $stmt->execute;
    }

    public function delete(string $table_name, ?array $where): void
    {
        $query = "DELETE FROM {$table_name}";

        if ($where === null) {
            $stmt = $this->mysqli->query($query);
        } else {
            $query       .= $this->getWhereQuery($where);
            $where_values = $this->getWhereValues($where);

            $stmt = $this->mysqli->prepare($query);
            $stmt = $this->bindParams($stmt, $where_values);
            $stmt->execute();
        }
    }

    private function bindParams(mysqli_stmt $stmt, ?array $values): mysqli_stmt
    {
        if (empty($values)) {
            return $stmt;
        }

        $types = '';
        foreach ($values as $value) {
            $type = gettype($value);
            if (!isset($this->bind_types[$type])) {
                throw new LogicException(__METHOD__ . "() '{$type}' is invalid");
            }

            $types .= $this->bind_types[$type];
        }

        $stmt->bind_param($types, ...$values);

        return $stmt;
    }

    private function getWhereQuery(array $where): string
    {
        if (!isset($where['condition'])) {
            throw new LogicException("'condition' parameter is required.");
        }

        return ' WHERE ' . $where['condition'];
    }

    public function getWhereValues(array $where): array
    {
        if (empty($where['values'])) {
            return [];
        }

        return $where['values'];
    }
}