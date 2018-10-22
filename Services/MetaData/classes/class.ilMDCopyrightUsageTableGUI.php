<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * @author Jesús López <lopez@leifos.com>
 * @version $Id$
 *
 * @ingroup ServicesMetaData
 */

class ilMDCopyrightUsageTableGUI extends ilTable2GUI
{
	/**
	 * @var integer
	 */
	protected $copyright_id;

	protected $db;

	/**
	 * ilCopyrightUsageGUI constructor.
	 * @param $a_parent_obj ilObjMDSettingsGUI
	 * @param $a_parent_cmd string
	 * @param $a_entity_id integer
	 */
	public function __construct($a_parent_obj, $a_parent_cmd='', $a_entity_id)
	{
		global $DIC;

		$this->db = $DIC->database();

		$this->copyright_id = $a_entity_id;

		$md_entry = new ilMDCopyrightSelectionEntry($a_entity_id);

		$this->setTitle($md_entry->getTitle());

		parent::__construct($a_parent_obj, $a_parent_cmd);

		$this->addColumn($this->lng->txt('object'),'object');
		$this->addColumn($this->lng->txt('meta_references'),'references');
		$this->addColumn($this->lng->txt('sub_items'),'subitems');
		$this->addColumn($this->lng->txt('owner'),'owner');

		$this->setRowTemplate("tpl.show_copyright_usages_row.html","Services/MetaData");

		$this->collectData();
	}

	function fillRow($a_set)
	{
		$this->tpl->setVariable('TITLE',$a_set['title']);
		$this->tpl->setVariable("DESCRIPTION", $a_set['desc']);
		if($a_set['references'])
		{
			$this->tpl->setCurrentBlock("references");
			$this->tpl->setVariable("REFERENCES",implode("<br>",$a_set['references']));
			$this->tpl->parseCurrentBlock();
		}

		$this->tpl->setVariable('SUB_ITEMS','TO DO');
		if($a_set['owner_link'])
		{
			$this->tpl->setCurrentBlock("link_owner");
			$this->tpl->setVariable("OWNER_LINK", $a_set['owner_link']);
			$this->tpl->setVariable('OWNER',$a_set['owner_name']);
			$this->tpl->parseCurrentBlock();
		} else {
			$this->tpl->setCurrentBlock("owner");
			$this->tpl->setVariable('OWNER',$a_set['owner_name']);
			$this->tpl->parseCurrentBlock();
		}

	}

	function collectData()
	{
		$db_data = $this->getDataFromDB();
		$data = array();
		foreach($db_data as $item)
		{
			$obj_id = $item['obj_id'];
			$data[] = array(
				"title" => ilObject::_lookupTitle($obj_id),
				"desc" => ilObject::_lookupDescription($obj_id),
				"references" => ilObject::_getAllReferences($obj_id),
				"owner_name" => ilUserUtil::getNamePresentation(ilObject::_lookupOwner($obj_id)),
				"owner_link" => ilUserUtil::getProfileLink(ilObject::_lookupOwner($obj_id))
			);
		}

		$this->setData($data);
	}

	public function getDataFromDB()
	{
		//TODO I DON'T NEED ALL THIS COLUMNS NOR COUNTER
		$query = "SELECT count(meta_rights_id) used, rbac_id, obj_id, obj_type FROM il_meta_rights ".
			"WHERE description = ".$this->db->quote('il_copyright_entry__'.IL_INST_ID.'__'.$this->copyright_id,'text');

		$result = $this->db->query($query);
		$data = array();
		while ($row = $this->db->fetchAssoc($result))
		{
			$data[] = array(
				"usages" => $row['used'],
				"obj_id" =>$row['obj_id'],
				"obj_type" => $row['obj_type']
			);
		}
		return $data;
	}
}