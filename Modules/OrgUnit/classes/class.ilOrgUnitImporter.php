<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */
require_once("./Services/Export/classes/class.ilXmlImporter.php");
/**
 * Class ilOrgUnitImporter
 *
 * @author: Oskar Truffer <ot@studer-raimann.ch>
 * @author: Martin Studer <ms@studer-raimann.ch>
 *
 */
class ilOrgUnitImporter extends ilXmlImporter {

	/** @var  array lang_var => language variable, import_id => the reference or import id, depending on the ou_id_type */
	public $errors;

	/** @var  array lang_var => language variable, import_id => the reference or import id, depending on the ou_id_type */
	public $warnings;

	/** @var array keys in {updated, edited, deleted} */
	public $stats;


	private function buildRef($id, $type){
		if($type == "reference_id"){
			if(!ilObjOrgUnit::_exists($id, true))
				return false;
			return $id;
		}
		elseif($type == "external_id"){
			$obj_id = ilObject::_lookupObjIdByImportId($id);
			$ref_ids = ilObject::_getAllReferences($obj_id);
			if(!count($ref_ids))
				return false;
			return array_shift($ref_ids);
		}else
			return false;
	}

	/**
	 * @param $importer ilOrgUnitImporter
	 */
	public function displayImportResults($importer) {
		if (! $importer->hasErrors() && ! $importer->hasWarnings()) {
			$stats = $importer->getStats();
			ilUtil::sendSuccess(sprintf($this->lng->txt("import_successful"), $stats["created"], $stats["updated"], $stats["deleted"]), true);
		}
		if ($importer->hasWarnings()) {
			$msg = $this->lng->txt("import_terminated_with_warnings") . ":<br>";
			foreach ($importer->getWarnings() as $warning) {
				$msg .= "-" . $this->lng->txt($warning["lang_var"]) . " (import id: " . $warning["import_id"] . ")<br>";
			}
			ilUtil::sendInfo($msg, true);
		}
		if ($importer->hasErrors()) {
			$msg = $this->lng->txt("import_terminated_with_errors") . ":<br>";
			foreach ($importer->getErrors() as $warning) {
				$msg .= "-" . $this->lng->txt($warning["lang_var"]) . " (import id: " . $warning["import_id"] . ")<br>";
			}
			ilUtil::sendFailure($msg, true);
		}
	}

	public function hasErrors(){
		return count($this->errors)!=0;
	}

	public function hasWarnings(){
		return count($this->warnings)!=0;
	}

	public function addWarning($lang_var, $import_id, $action = null){
		$this->warnings[] = array("lang_var" => $lang_var, "import_id" => $import_id, "action" => $action);
	}

	public function addError($lang_var, $import_id, $action = null){
		$this->errors[] = array("lang_var" => $lang_var, "import_id" => $import_id, "action" => $action);
	}

	/**
	 * @return array
	 */
	public function getErrors()
	{
		return $this->errors;
	}

	/**
	 * @return array
	 */
	public function getWarnings()
	{
		return $this->warnings;
	}

	/**
	 * @return array
	 */
	public function getStats()
	{
		return $this->stats;
	}

	/**
	 * Import XML
	 *
	 * @param
	 * @return
	 */
	function importXmlRepresentation($a_entity, $a_id, $a_xml, $a_mapping)
	{

	}
}
?>