<?php namespace Raahul\LarryFour\Command;

use \Raahul\LarryFour\Generator\ModelGenerator;
use \Raahul\LarryFour\ModelList;
use \Raahul\LarryFour\MigrationList;
use \Raahul\LarryFour\DbParser;
use \Raahul\SchemaExtractor\SchemaExtractor;
use \Illuminate\Support\Facades\DB;

class GenerateFromDbWithModel extends GenerateFromDb {

    protected $name = 'larry:fromdbwithmodel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate migration files and model files from the active database';

	/**
	 * Instance of the migration generator
	 * @var \Raahul\LarryFour\Generator\ModelGenerator
	 */
	protected $modelGenerator;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

	    // Initialize the model generator
	    $this->modelGenerator = new ModelGenerator();

        $this->dbParser = new DbParser(new MigrationList(), new ModelList(), new SchemaExtractor(), 'mysql');
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        // Verify whether we're running on a supported driver
        $this->verifyDriver();

        // Get all tables to be processed
        $tables = $this->getAllTablesToBeProcessed();

        // Print it for confirmation
	    $this->info("Migrations and models for the following tables will be created:");
	    $this->info(implode("\n", $tables));
        $confirm = $this->confirm('Do you wish to continue? [yes|no]', false);

        if (!$confirm) die();

        // Now, let's start processing
        $tablesData = array();
        foreach ($tables as $table)
        {
            if( DB::getDriverName() === 'mysql' ) {
                $tablesData[ $table ] = DB::select("DESCRIBE `$table`");
            } else if( DB::getDriverName() === 'pgsql' ) {
                // PostgreSQL port of MySQL DESCRIBE statement
                $tablesData[ $table ] = DB::select(
                    "SELECT a.oid, c.column_name AS \"Field\", COALESCE(c.column_default, 'NULL') AS \"Default\", c.is_nullable AS \"Null\",
                        CASE
                          WHEN c.udt_name = 'bool' THEN 'boolean'
                          WHEN c.udt_name = 'int2' THEN 'smallint' || COALESCE(NULLIF('('|| COALESCE(c.character_maximum_length::varchar, '') || ')', '()'), '')
                          WHEN c.udt_name = 'int4' THEN 'int'
                          WHEN c.udt_name = 'int8' THEN 'bigint'
                          WHEN c.udt_name LIKE 'float_' THEN 'float'
                          WHEN c.udt_name = 'timetz' THEN 'time'
                          WHEN c.udt_name = 'timestamptz' THEN 'timestamp'
                          WHEN c.udt_name ~ '_?bytea' THEN 'blob' || COALESCE(NULLIF('('|| COALESCE(c.character_maximum_length::varchar, '') || ')', '()'), '')
                          WHEN c.udt_name = 'numeric' THEN 'decimal' || COALESCE(NULLIF('('|| COALESCE(c.numeric_precision::varchar, '') || ',' || COALESCE(c.numeric_scale::varchar, '') || ')', '(,)'), '')
                          WHEN p.consrc LIKE '%ARRAY[%]%' THEN 'enum' || '(' || regexp_replace(regexp_replace(p.consrc, '.+ARRAY\[([^\[\]]+)\].+', '\\1'), '::[a-z0-9 ]+', '', 'g') || ')'
                        ELSE c.udt_name || COALESCE(NULLIF('(' || COALESCE(c.character_maximum_length::varchar, '') ||')', '()'), '')
                        END AS \"Type\",
                        CASE
                          WHEN c.column_default LIKE 'nextval(%)' THEN 'auto_increment'
                        END AS \"Extra\",
                        CASE
                          WHEN p.contype = 'p' THEN 'PRI'
                          WHEN p.contype = 'u' AND p.conkey::text LIKE '{_,%}' THEN 'MUL'
                          WHEN p.contype = 'u' THEN 'UNI'
                        END AS \"Key\"
                        FROM INFORMATION_SCHEMA.COLUMNS c
                        INNER JOIN (SELECT attrelid AS oid, attname FROM pg_attribute
                          WHERE attrelid = ( SELECT oid FROM pg_class WHERE relname = '$table') ) a ON a.attname = c.column_name
                          LEFT JOIN pg_constraint p ON p.conrelid = a.oid AND c.dtd_identifier::integer = ANY (p.conkey )
                        WHERE c.table_name = '$table'"
                );
            }
        }

        // Get migrations
	    $parsed = $this->dbParser->parse($tablesData, true);

	    // Generate migrations
	    // $this->generateMigrations($parsed['migrationList']->all());

	    // Generate models
	    $this->generateModels($parsed['modelList']->all());
    }

	/**
	 * Generates all the models, given a list of models
	 * @param  array $models An array of model objects
	 */
	protected function generateModels($models)
	{
		foreach ($models as $model)
		{
			$this->larryWriter->writeModel(
				$this->modelGenerator->generate($model),
				$model->modelName . '.php'
			);
			$this->info("Wrote model: " . $model->modelName . '.php');
		}
	}

}
