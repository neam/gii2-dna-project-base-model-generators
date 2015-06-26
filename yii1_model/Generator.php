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
                echo "$tableName does not exist\n";
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
                'relations' => isset($relations[$className]) ? $relations[$className] : [],
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

    protected $buildRelations = true;

	protected function generateRelations()
	{
		if(!$this->buildRelations)
			return array();

		$schemaName='';
		if(($pos=strpos($this->tableName,'.'))!==false)
			$schemaName=substr($this->tableName,0,$pos);

		$relations=array();
        $db = $this->getDbConnection();
        foreach ($this->getTableNames() as $tableName) {
            $table = $db->getTableSchema($tableName);
            if (empty($table)) {
                echo "$tableName does not exist\n";
                continue;
            }
			if($this->tablePrefix!='' && strpos($table->name,$this->tablePrefix)!==0)
				continue;
			$tableName=$table->name;

			if ($this->isRelationTable($table))
			{
				$pks=$table->primaryKey;
				$fks=$table->foreignKeys;

				$table0=$fks[$pks[0]][0];
				$table1=$fks[$pks[1]][0];
				$className0=$this->generateClassName($table0);
				$className1=$this->generateClassName($table1);

				$unprefixedTableName=$this->removePrefix($tableName);

				$relationName=$this->generateRelationName($table0, $table1, true);
				$relations[$className0][$relationName]="array(self::MANY_MANY, '$className1', '$unprefixedTableName($pks[0], $pks[1])')";

				$relationName=$this->generateRelationName($table1, $table0, true);

				$i=1;
				$rawName=$relationName;
				while(isset($relations[$className1][$relationName]))
					$relationName=$rawName.$i++;

				$relations[$className1][$relationName]="array(self::MANY_MANY, '$className0', '$unprefixedTableName($pks[1], $pks[0])')";
			}
			else
			{
				$className=$this->generateClassName($tableName);
				foreach ($table->foreignKeys as $k => $fk)
				{
                    $fkName = array_keys($fk)[1];
                    $fkEntry = array_values($fk);

					// Put table and key name in variables for easier reading
					$refTable=$fkEntry[0]; // Table name that current fk references to
					$refKey=$fkEntry[1];   // Key in that table being referenced
					$refClassName=$this->generateClassName($refTable);

					// Add relation for this table
					$relationName=$this->generateRelationName($tableName, $fkName, false);
					$relations[$className][$relationName]="array(self::BELONGS_TO, '$refClassName', '$fkName')";

					// Add relation for the referenced table
					$relationType=$table->primaryKey === $fkName ? 'HAS_ONE' : 'HAS_MANY';
					$relationName=$this->generateRelationName($refTable, $this->removePrefix($tableName,false), $relationType==='HAS_MANY');
					$i=1;
					$rawName=$relationName;
					while(isset($relations[$refClassName][$relationName]))
						$relationName=$rawName.($i++);
					$relations[$refClassName][$relationName]="array(self::$relationType, '$className', '$fkName')";
				}
			}
		}
		return $relations;
	}

	protected function removePrefix($tableName,$addBrackets=true)
	{
        $db = $this->getDbConnection();
		if($addBrackets && $db->tablePrefix=='')
			return $tableName;
		$prefix=$this->tablePrefix!='' ? $this->tablePrefix : $db->tablePrefix;
		if($prefix!='')
		{
			if($addBrackets && $db->tablePrefix!='')
			{
				$prefix=$db->tablePrefix;
				$lb='{{';
				$rb='}}';
			}
			else
				$lb=$rb='';
			if(($pos=strrpos($tableName,'.'))!==false)
			{
				$schema=substr($tableName,0,$pos);
				$name=substr($tableName,$pos+1);
				if(strpos($name,$prefix)===0)
					return $schema.'.'.$lb.substr($name,strlen($prefix)).$rb;
			}
			elseif(strpos($tableName,$prefix)===0)
				return $lb.substr($tableName,strlen($prefix)).$rb;
		}
		return $tableName;
	}

	/**
	 * Checks if the given table is a "many to many" pivot table.
	 * Their PK has 2 fields, and both of those fields are also FK to other separate tables.
	 * @param CDbTableSchema table to inspect
	 * @return boolean true if table matches description of helpter table.
	 */
	protected function isRelationTable($table)
	{
		$pk=$table->primaryKey;
		return (count($pk) === 2 // we want 2 columns
			&& isset($table->foreignKeys[$pk[0]]) // pk column 1 is also a foreign key
			&& isset($table->foreignKeys[$pk[1]]) // pk column 2 is also a foriegn key
			&& $table->foreignKeys[$pk[0]][0] !== $table->foreignKeys[$pk[1]][0]); // and the foreign keys point different tables
	}

    /*
	protected function generateClassName($tableName)
	{
		if($this->tableName===$tableName || ($pos=strrpos($this->tableName,'.'))!==false && substr($this->tableName,$pos+1)===$tableName)
			return $this->modelClass;

		$tableName=$this->removePrefix($tableName,false);
		if(($pos=strpos($tableName,'.'))!==false) // remove schema part (e.g. remove 'public2.' from 'public2.post')
			$tableName=substr($tableName,$pos+1);
		$className='';
		foreach(explode('_',$tableName) as $name)
		{
			if($name!=='')
				$className.=ucfirst($name);
		}
		return $className;
	}
    */

	/**
	 * Generate a name for use as a relation name (inside relations() function in a model).
	 * @param string the name of the table to hold the relation
	 * @param string the foreign key name
	 * @param boolean whether the relation would contain multiple objects
	 * @return string the relation name
	 */
	protected function generateRelationName($tableName, $fkName, $multiple)
	{
		if(strcasecmp(substr($fkName,-2),'id')===0 && strcasecmp($fkName,'id'))
			$relationName=rtrim(substr($fkName, 0, -2),'_');
		else
			$relationName=$fkName;

		$relationName[0]=strtolower($relationName);

		if($multiple)
			$relationName=Inflector::pluralize($relationName);

		$names=preg_split('/_+/',$relationName,-1,PREG_SPLIT_NO_EMPTY);
		if(empty($names)) return $relationName;  // unlikely
		for($name=$names[0], $i=1;$i<count($names);++$i)
			$name.=ucfirst($names[$i]);

		$rawName=$name;
        $db = $this->getDbConnection();
        $table = $db->getTableSchema($tableName);
		$i=0;
		while(isset($table->columns[$name]))
			$name=$rawName.($i++);

		return $name;
	}

    public function generateRules($table)
    {
        $enum_constants = $this->getEnum($table->columns);

        $rules     = array();
        $required  = array();
        $null      = array();
        $integers  = array();
        $numerical = array();
        $length    = array();
        $safe      = array();
        $float      = array();

        foreach ($table->columns as $column) {
            if ($column->isPrimaryKey && $table->sequenceName !== null) {
                continue;
            }
            $r = !$column->allowNull && $column->defaultValue === null;
            if ($r) {
                $required[] = $column->name;
            } else {
                $null[] = $column->name;
            }

            if ($column->type === 'integer') {
                $integers[] = $column->name;
            } elseif ($column->type === 'double') {
                $numerical[] = $column->name;
            } elseif(substr(strtoupper($column->dbType), 0, 4) == 'ENUM') {
                continue;
            } elseif(substr(strtoupper($column->dbType), 0, 7) == 'DECIMAL') {
                $float[] = $column->name;
                $length[$column->size+1][] = $column->name;
            } elseif ($column->type === 'string' && $column->size > 0) {
                $length[$column->size][] = $column->name;
            } elseif (!$column->isPrimaryKey && !$r) {
                $safe[] = $column->name;
            }
        }


        if ($required !== array()) {
            $rules[] = "array('" . implode(', ', $required) . "', 'required')";
        }
        if ($null !== array()) {
            $rules[] = "array('" . implode(', ', $null) . "', 'default', 'setOnEmpty' => true, 'value' => null)";
        }
        if ($integers !== array()) {
            $rules[] = "array('" . implode(', ', $integers) . "', 'numerical', 'integerOnly' => true)";
        }
        if ($numerical !== array()) {
            $rules[] = "array('" . implode(', ', $numerical) . "', 'numerical')";
        }

        if ($float !== array()) {
            $rules[] = "array('" . implode(', ', $float) . "', 'type','type'=>'float')";
        }
        if ($length !== array()) {
            foreach ($length as $len => $cols) {
                $rules[] = "array('" . implode(', ', $cols) . "', 'length', 'max' => $len)";
            }
        }
        if ($safe !== array()) {
            $rules[] = "array('" . implode(', ', $safe) . "', 'safe')";
        }

        if ($enum_constants !== array()) {
            foreach($enum_constants as $field_name => $field_details){
                $ea = array();
                foreach($field_details as $field_enum_values){
                    $ea[] = 'self::'.$field_enum_values['const_name'];
                }
                $rules[] = "array('" .$field_name . "', 'in', 'range' => array(" . implode(", ",$ea) . "))";
            }
        }


        return $rules;
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

}
