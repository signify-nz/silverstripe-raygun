<?php

class DBTableChecker
{
	/**
	 * @var boolean[]
	 */
	private static $database_is_ready = [];

	/**
	 * Check if all tables and fields for an object exist in the database.
	 *
	 * @param string $class
	 * @return boolean
	 */
	public static function databaseIsReady(string $class): bool
	{
		if (!is_subclass_of($class, DataObject::class)) {
			return true;
		}

		// Don't check again if we already know the db is ready.
		if (!empty(self::$database_is_ready[$class])) {
			return true;
		}

		// Check if all tables and fields required for the class exist in the database.
		$requiredClasses = ClassInfo::dataClassesFor($class);
		$schema = DB::get_schema();
		foreach ($requiredClasses as $required) {
			// Skip test classes, as not all test classes are scaffolded at once
			if (is_a($required, TestOnly::class, true)) {
				continue;
			}

			if (!ClassInfo::hasTable($required)) {
				return false;
			}

			// HACK: DataExtensions aren't applied until a class is instantiated for
			// the first time, so create an instance here.
			singleton($required);

			// if any of the tables don't have all fields mapped as table columns
			$dbFields = DB::field_list($required);
			if (!$dbFields) {
				return false;
			}

			$objFields = $schema->fieldList($required);
			$missingFields = array_diff_key($objFields, $dbFields);

			if ($missingFields) {
				return false;
			}
		}
		self::$database_is_ready[$class] = true;

		return true;
	}
}
