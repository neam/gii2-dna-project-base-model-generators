<?php

namespace neam\gii2_dna_project_base_generators\yii1_model;

use Yii;
use yii\base\Exception;
use yii\gii\CodeFile;
use yii\helpers\Inflector;

class Generator extends \schmunk42\giiant\model\Generator
{

    /**
     * Generate models for specific tables only
     * @var
     */
    public $tables;

    /**
     *
     */
    public $useMetadataClass = true;

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'Yii DNA project base model code';
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return 'This generator generates an Yii 1 ActiveRecord class, metadata and base class for the specified database table.';
    }

    /**
     * @inheritdoc
     */
    public function requiredTemplates()
    {
        return ['basemodel.php', 'metadatamodel.php', 'model.php'];
    }

    /**
     * @inheritdoc
     */
    public function generate()
    {
        $files = [];
        $relations = $this->generateRelations();
        $db = $this->getDbConnection();
        foreach ($this->getTableNames() as $tableName) {

            $className = $this->generateClassName($tableName);
            $tableSchema = $db->getTableSchema($tableName);

            if (empty($tableSchema)) {
                continue;
                //throw new Exception("Empty \$tableSchema for table $tableName");
            }

            $params = [
                'tableName' => $tableName,
                'className' => $className,
                'tableSchema' => $tableSchema,
                'baseClassTraits' => '',
                'metadataClassTraits' => \ItemTypes::exists($className) ? $className . "Trait" : "",
                'labels' => $this->generateLabels($tableSchema),
                'rules' => $this->generateRules($tableSchema),
                'relations' => isset($relations[$tableName]) ? $relations[$tableName] : [],
                'ns' => $this->ns,
                'enum' => $this->getEnum($tableSchema->columns),
                // legacy yii1 gtc
                'messageCatalog' => 'model',
            ];

            $files[] = new CodeFile(
                Yii::getAlias('@' . str_replace('\\', '/', $this->ns)) . '/base/Base' . $className . '.php',
                $this->render('basemodel.php', $params)
            );

            $modelClassFile = Yii::getAlias('@' . str_replace('\\', '/', $this->ns)) . '/' . $className . '.php';
            if ($this->generateModelClass || !is_file($modelClassFile)) {
                $files[] = new CodeFile(
                    $modelClassFile,
                    $this->render('model.php', $params)
                );
            }

            if ($this->useMetadataClass) {
                $files[] = new CodeFile(
                    Yii::getAlias('@' . str_replace('\\', '/', $this->ns)) . '/metadata/Metadata' . $className . '.php',
                    $this->render('metadatamodel.php', $params)
                );
            }


        }
        return $files;
    }

    protected function getTableNames()
    {
        $this->tableNames = $this->tables;
        return parent::getTableNames();
    }


    /**
     * Generates a class name from the specified table name.
     *
     * @param string $tableName the table name (which may contain schema prefix)
     *
     * @return string the generated class name
     */
    protected function generateClassName($tableName, $useSchemaName = null)
    {

        #Yii::trace("Generating class name for '{$tableName}'...", __METHOD__);
        if (isset($this->classNames2[$tableName])) {
            #Yii::trace("Using '{$this->classNames2[$tableName]}' for '{$tableName}' from classNames2.", __METHOD__);
            return $this->classNames2[$tableName];
        }

        if (isset($this->tableNameMap[$tableName])) {
            Yii::trace("Converted '{$tableName}' from tableNameMap.", __METHOD__);
            return $this->classNames2[$tableName] = $this->tableNameMap[$tableName];
        }

        if (($pos = strrpos($tableName, '.')) !== false) {
            $tableName = substr($tableName, $pos + 1);
        }

        $db = $this->getDbConnection();
        $patterns = [];
        $patterns[] = "/^{$this->tablePrefix}(.*?)$/";
        $patterns[] = "/^(.*?){$this->tablePrefix}$/";
        $patterns[] = "/^{$db->tablePrefix}(.*?)$/";
        $patterns[] = "/^(.*?){$db->tablePrefix}$/";

        if (strpos($this->tableName, '*') !== false) {
            $pattern = $this->tableName;
            if (($pos = strrpos($pattern, '.')) !== false) {
                $pattern = substr($pattern, $pos + 1);
            }
            $patterns[] = '/^' . str_replace('*', '(\w+)', $pattern) . '$/';
        }

        $className = $tableName;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $tableName, $matches)) {
                $className = $matches[1];
                Yii::trace("Mapping '{$tableName}' to '{$className}' from pattern '{$pattern}'.", __METHOD__);
                break;
            }
        }

        $returnName = Inflector::id2camel($className, '_');
        Yii::trace("Converted '{$tableName}' to '{$returnName}'.", __METHOD__);
        return $this->classNames2[$tableName] = $returnName;
    }

    protected function generateRelations()
    {
        $relations = parent::generateRelations();

        // inject namespace
        $ns = "\\{$this->ns}\\";
        foreach ($relations AS $model => $relInfo) {
            foreach ($relInfo AS $relName => $relData) {

                $relations[$model][$relName][0] = preg_replace(
                    '/(has[A-Za-z0-9]+\()([a-zA-Z0-9]+::)/',
                    '$1__NS__$2',
                    $relations[$model][$relName][0]
                );
                $relations[$model][$relName][0] = str_replace('__NS__', $ns, $relations[$model][$relName][0]);
            }
        }
        return $relations;
    }

    /**
     * prepare ENUM field values
     * @param array $columns
     * @return array
     */
    public function getEnum($columns)
    {

        $enum = [];
        foreach ($columns as $column) {
            if (!$this->isEnum($column)) {
                continue;
            }

            $column_camel_name = str_replace(' ', '', ucwords(implode(' ', explode('_', $column->name))));
            $enum[$column->name]['func_opts_name'] = 'opts' . $column_camel_name;
            $enum[$column->name]['func_get_label_name'] = 'get' . $column_camel_name . 'ValueLabel';
            $enum[$column->name]['values'] = [];

            $enum_values = explode(',', substr($column->dbType, 4, strlen($column->dbType) - 1));

            foreach ($enum_values as $value) {

                $value = trim($value, "()'");

                $const_name = strtoupper($column->name . '_' . $value);
                $const_name = preg_replace('/\s+/', '_', $const_name);
                $const_name = str_replace(['-', '_', ' '], '_', $const_name);
                $const_name = preg_replace('/[^A-Z0-9_]/', '', $const_name);

                $label = ucwords(
                    trim(strtolower(str_replace(['-', '_'], ' ', preg_replace('/(?<![A-Z])[A-Z]/', ' \0', $value))))
                );
                $label = preg_replace('/\s+/', ' ', $label);

                $enum[$column->name]['values'][] = [
                    'value' => $value,
                    'const_name' => $const_name,
                    'label' => $label,
                ];

            }
        }
        return $enum;

    }

    /**
     * validate is ENUM
     * @param  $column table column
     * @return type
     */
    public function isEnum($column)
    {
        return substr(strtoupper($column->dbType), 0, 4) == 'ENUM';
    }


    /**
     * Generates validation rules for the specified table and add enum value validation.
     * @param \yii\db\TableSchema $table the table schema
     * @return array the generated validation rules
     */
    public function generateRules($table)
    {
        $rules = [];

        //for enum fields create rules "in range" for all enum values
        $enum = $this->getEnum($table->columns);
        foreach ($enum as $field_name => $field_details) {
            $ea = array();
            foreach ($field_details['values'] as $field_enum_values) {
                $ea[] = 'self::' . $field_enum_values['const_name'];
            }
            $rules[] = "['" . $field_name . "', 'in', 'range' => [\n                    " . implode(
                    ",\n                    ",
                    $ea
                ) . ",\n                ]\n            ]";
        }

        return array_merge(parent::generateRules($table), $rules);
    }

}
