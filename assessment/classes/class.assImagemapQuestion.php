<?php
 /*
   +----------------------------------------------------------------------------+
   | ILIAS open source                                                          |
   +----------------------------------------------------------------------------+
   | Copyright (c) 1998-2001 ILIAS open source, University of Cologne           |
   |                                                                            |
   | This program is free software; you can redistribute it and/or              |
   | modify it under the terms of the GNU General Public License                |
   | as published by the Free Software Foundation; either version 2             |
   | of the License, or (at your option) any later version.                     |
   |                                                                            |
   | This program is distributed in the hope that it will be useful,            |
   | but WITHOUT ANY WARRANTY; without even the implied warranty of             |
   | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the              |
   | GNU General Public License for more details.                               |
   |                                                                            |
   | You should have received a copy of the GNU General Public License          |
   | along with this program; if not, write to the Free Software                |
   | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA. |
   +----------------------------------------------------------------------------+
*/

include_once "./assessment/classes/class.assQuestion.php";
include_once "./assessment/classes/inc.AssessmentConstants.php";

/**
* Class for image map questions
*
* assImagemapQuestion is a class for imagemap question.
*
* @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
* @version	$Id$
* @module   class.assImagemapQuestion.php
* @modulegroup   Assessment
*/
class assImagemapQuestion extends assQuestion 
{

/**
* The imagemap_Question containing the question
*
* The imagemap_Question containing the question.
*
* @var string
*/
  var $question;

/**
* The possible answers of the imagemap question
*
* $answers is an array of the predefined answers of the imagemap question
*
* @var array
*/
  var $answers;

/**
* The imagemap file containing the name of imagemap file
*
* The imagemap file containing the name of imagemap file
*
* @var string
*/
  var $imagemap_filename;

/**
* The image file containing the name of image file
*
* The image file containing the name of image file
*
* @var string
*/
  var $image_filename;

/**
* The variable containing contents of an imagemap file
*
* The variable containing contents of an imagemap file
*
* @var string
*/
  var $imagemap_contents;
	var $coords;

/**
* assImagemapQuestion constructor
*
* The constructor takes possible arguments an creates an instance of the assImagemapQuestion object.
*
* @param string $title A title string to describe the question
* @param string $comment A comment string to describe the question
* @param string $author A string containing the name of the questions author
* @param integer $owner A numerical ID to identify the owner/creator
* @param string $imagemap_file The imagemap file name of the imagemap question
* @param string $image_file The image file name of the imagemap question
* @param string $question The question string of the imagemap question
* @access public
*/
  function assImagemapQuestion(
    $title = "",
    $comment = "",
    $author = "",
    $owner = -1,
    $question = "",
    $imagemap_filename = "",
    $image_filename = ""

  )
  {
    $this->assQuestion($title, $comment, $author, $owner);
    $this->question = $question;
    $this->imagemap_filename = $imagemap_filename;
    $this->image_filename = $image_filename;
    $this->answers = array();
		$this->coords = array();
  }

/**
* Returns true, if a imagemap question is complete for use
*
* Returns true, if a imagemap question is complete for use
*
* @return boolean True, if the imagemap question is complete for use, otherwise false
* @access public
*/
	function isComplete()
	{
		if (($this->title) and ($this->author) and ($this->question) and ($this->image_filename) and (count($this->answers)) and ($this->getMaximumPoints() > 0))
		{
			return true;
		}
			else
		{
			return false;
		}
	}

	/**
	* Saves a assImagemapQuestion object to a database
	*
	* Saves a assImagemapQuestion object to a database (experimental)
	*
	* @param object $db A pear DB object
	* @access public
	*/
	function saveToDb($original_id = "")
	{
		global $ilias;

		$complete = 0;
		if ($this->isComplete())
		{
			$complete = 1;
		}

		$db = & $ilias->db;

		$estw_time = $this->getEstimatedWorkingTime();
		$estw_time = sprintf("%02d:%02d:%02d", $estw_time['h'], $estw_time['m'], $estw_time['s']);
		if ($original_id)
		{
			$original_id = $db->quote($original_id);
		}
		else
		{
			$original_id = "NULL";
		}

		if ($this->id == -1)
		{
			// Neuen Datensatz schreiben
			$now = getdate();
			$question_type = $this->getQuestionType();
			$created = sprintf("%04d%02d%02d%02d%02d%02d", $now['year'], $now['mon'], $now['mday'], $now['hours'], $now['minutes'], $now['seconds']);
			$query = sprintf("INSERT INTO qpl_questions (question_id, question_type_fi, obj_fi, title, comment, author, owner, question_text, working_time, points, complete, created, original_id, TIMESTAMP) VALUES (NULL, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NULL)",
				$db->quote($question_type),
				$db->quote($this->obj_id),
				$db->quote($this->title),
				$db->quote($this->comment),
				$db->quote($this->author),
				$db->quote($this->owner),
				$db->quote($this->question),
				$db->quote($estw_time),
				$db->quote($this->getMaximumPoints() . ""),
				$db->quote("$complete"),
				$db->quote($created),
				$original_id
			);
			$result = $db->query($query);
			if ($result == DB_OK)
			{
				$this->id = $db->getLastInsertId();
				$insertquery = sprintf("INSERT INTO qpl_question_imagemap (question_fi, image_file) VALUES (%s, %s)",
					$db->quote($this->id . ""),
					$db->quote($this->image_filename)
				);
				$db->query($insertquery);
				// create page object of question
				$this->createPageObject();

				// Falls die Frage in einen Test eingef�gt werden soll, auch diese Verbindung erstellen
				if ($this->getTestId() > 0)
				{
					$this->insertIntoTest($this->getTestId());
				}
			}
		}
		else
		{
			// Vorhandenen Datensatz aktualisieren
			$query = sprintf("UPDATE qpl_questions SET obj_fi = %s, title = %s, comment = %s, author = %s, question_text = %s, working_time = %s, points = %s, complete = %s WHERE question_id = %s",
				$db->quote($this->obj_id. ""),
				$db->quote($this->title),
				$db->quote($this->comment),
				$db->quote($this->author),
				$db->quote($this->question),
				$db->quote($estw_time),
				$db->quote($this->getMaximumPoints() . ""),
				$db->quote("$complete"),
				$db->quote($this->id)
			);
			$result = $db->query($query);
			$query = sprintf("UPDATE qpl_question_imagemap SET image_file = %s WHERE question_fi = %s",
				$db->quote($this->image_filename),
				$db->quote($this->id)
			);
			$result = $db->query($query);
				
		}

		if ($result == DB_OK)
		{
			$query = sprintf("DELETE FROM qpl_answer_imagemap WHERE question_fi = %s",
				$db->quote($this->id)
			);
			$result = $db->query($query);
			// Anworten wegschreiben
			foreach ($this->answers as $key => $value)
			{
				$answer_obj = $this->answers[$key];
				//print "id:".$this->id." answer tex:".$answer_obj->getAnswertext()." answer_obj->getOrder():".$answer_obj->getOrder()." answer_obj->getCoords():".$answer_obj->getCoords()." answer_obj->getArea():".$answer_obj->getArea();
				$query = sprintf("INSERT INTO qpl_answer_imagemap (answer_id, question_fi, answertext, points, aorder, correctness, coords, area) VALUES (NULL, %s, %s, %s, %s, %s, %s, %s)",
					$db->quote($this->id),
					$db->quote($answer_obj->getAnswertext() . ""),
					$db->quote($answer_obj->getPoints() . ""),
					$db->quote($answer_obj->getOrder() . ""),
					$db->quote($answer_obj->getState() . ""),
					$db->quote($answer_obj->getCoords() . ""),
					$db->quote($answer_obj->getArea() . "")
					);
				$answer_result = $db->query($query);
				}
		}
		parent::saveToDb($original_id);
	}

/**
* Duplicates an assImagemapQuestion
*
* Duplicates an assImagemapQuestion
*
* @access public
*/
	function duplicate($for_test = true, $title = "", $author = "", $owner = "")
	{
		if ($this->id <= 0)
		{
			// The question has not been saved. It cannot be duplicated
			return;
		}
		// duplicate the question in database
		$clone = $this;
		include_once ("./assessment/classes/class.assQuestion.php");
		$original_id = assQuestion::_getOriginalId($this->id);
		$clone->id = -1;
		if ($title)
		{
			$clone->setTitle($title);
		}
		if ($author)
		{
			$clone->setAuthor($author);
		}
		if ($owner)
		{
			$clone->setOwner($owner);
		}
		if ($for_test)
		{
			$clone->saveToDb($original_id);
		}
		else
		{
			$clone->saveToDb();
		}

		// copy question page content
		$clone->copyPageOfQuestion($original_id);

		// duplicate the image
		$clone->duplicateImage($original_id);
		return $clone->id;
	}

	/**
	* Copies an assImagemapQuestion object
	*
	* Copies an assImagemapQuestion object
	*
	* @access public
	*/
	function copyObject($target_questionpool, $title = "")
	{
		if ($this->id <= 0)
		{
			// The question has not been saved. It cannot be duplicated
			return;
		}
		// duplicate the question in database
		$clone = $this;
		include_once ("./assessment/classes/class.assQuestion.php");
		$original_id = assQuestion::_getOriginalId($this->id);
		$clone->id = -1;
		$source_questionpool = $this->getObjId();
		$clone->setObjId($target_questionpool);
		if ($title)
		{
			$clone->setTitle($title);
		}
		$clone->saveToDb();

		// copy question page content
		$clone->copyPageOfQuestion($original_id);

		// duplicate the image
		$clone->copyImage($original_id, $source_questionpool);
		return $clone->id;
	}
	
	function duplicateImage($question_id)
	{
		$imagepath = $this->getImagePath();
		$imagepath_original = str_replace("/$this->id/images", "/$question_id/images", $imagepath);
		if (!file_exists($imagepath)) {
			ilUtil::makeDirParents($imagepath);
		}
		$filename = $this->get_image_filename();
		if (!copy($imagepath_original . $filename, $imagepath . $filename)) {
			print "image could not be duplicated!!!! ";
		}
	}

	function copyImage($question_id, $source_questionpool)
	{
		$imagepath = $this->getImagePath();
		$imagepath_original = str_replace("/$this->id/images", "/$question_id/images", $imagepath);
		$imagepath_original = str_replace("/$this->obj_id/", "/$source_questionpool/", $imagepath_original);
		if (!file_exists($imagepath)) 
		{
			ilUtil::makeDirParents($imagepath);
		}
		$filename = $this->get_image_filename();
		if (!copy($imagepath_original . $filename, $imagepath . $filename)) 
		{
			print "image could not be copied!!!! ";
		}
	}

/**
* Loads a assImagemapQuestion object from a database
*
* Loads a assImagemapQuestion object from a database (experimental)
*
* @param object $db A pear DB object
* @param integer $question_id A unique key which defines the multiple choice test in the database
* @access public
*/
  function loadFromDb($question_id)
  {
    global $ilias;

    $db = & $ilias->db;
    $query = sprintf("SELECT qpl_questions.*, qpl_question_imagemap.* FROM qpl_questions, qpl_question_imagemap WHERE question_id = %s AND qpl_questions.question_id = qpl_question_imagemap.question_fi",
      $db->quote($question_id)
    );
    $result = $db->query($query);
    if (strcmp(strtolower(get_class($result)), db_result) == 0) {
      if ($result->numRows() == 1) {
        $data = $result->fetchRow(DB_FETCHMODE_OBJECT);
        $this->id = $question_id;
				$this->obj_id = $data->obj_fi;
        $this->title = $data->title;
        $this->comment = $data->comment;
        $this->author = $data->author;
				$this->original_id = $data->original_id;
				$this->solution_hint = $data->solution_hint;
        $this->owner = $data->owner;
        $this->question = $data->question_text;
        $this->image_filename = $data->image_file;
        $this->points = $data->points;
        $this->setEstimatedWorkingTime(substr($data->working_time, 0, 2), substr($data->working_time, 3, 2), substr($data->working_time, 6, 2));
      }
      $query = sprintf("SELECT * FROM qpl_answer_imagemap WHERE question_fi = %s ORDER BY aorder ASC",
        $db->quote($question_id)
      );
      $result = $db->query($query);
			include_once "./assessment/classes/class.assAnswerImagemap.php";
      if (strcmp(strtolower(get_class($result)), db_result) == 0) 
			{
        while ($data = $result->fetchRow(DB_FETCHMODE_OBJECT)) 
				{
          array_push($this->answers, new ASS_AnswerImagemap($data->answertext, $data->points, $data->aorder, $data->coords, $data->area));
        }
      }
    }
		parent::loadFromDb($question_id);
  }

	/**
	* Returns a QTI xml representation of the question
	*
	* Returns a QTI xml representation of the question and sets the internal
	* domxml variable with the DOM XML representation of the QTI xml representation
	*
	* @return string The QTI xml representation of the question
	* @access public
	*/
	function to_xml($a_include_header = true, $a_include_binary = true, $a_shuffle = false, $test_output = false, $force_image_references = false)
	{
		include_once("./classes/class.ilXmlWriter.php");
		$a_xml_writer = new ilXmlWriter;
		// set xml header
		$a_xml_writer->xmlHeader();
		$a_xml_writer->xmlStartTag("questestinterop");
		$attrs = array(
			"ident" => "il_".IL_INST_ID."_qst_".$this->getId(),
			"title" => $this->getTitle()
		);
		$a_xml_writer->xmlStartTag("item", $attrs);
		// add question description
		$a_xml_writer->xmlElement("qticomment", NULL, $this->getComment());
		// add estimated working time
		$workingtime = $this->getEstimatedWorkingTime();
		$duration = sprintf("P0Y0M0DT%dH%dM%dS", $workingtime["h"], $workingtime["m"], $workingtime["s"]);
		$a_xml_writer->xmlElement("duration", NULL, $duration);
		// add ILIAS specific metadata
		$a_xml_writer->xmlStartTag("itemmetadata");
		$a_xml_writer->xmlStartTag("qtimetadata");
		$a_xml_writer->xmlStartTag("qtimetadatafield");
		$a_xml_writer->xmlElement("fieldlabel", NULL, "ILIAS_VERSION");
		$a_xml_writer->xmlElement("fieldentry", NULL, $this->ilias->getSetting("ilias_version"));
		$a_xml_writer->xmlEndTag("qtimetadatafield");
		$a_xml_writer->xmlStartTag("qtimetadatafield");
		$a_xml_writer->xmlElement("fieldlabel", NULL, "QUESTIONTYPE");
		$a_xml_writer->xmlElement("fieldentry", NULL, IMAGEMAP_QUESTION_IDENTIFIER);
		$a_xml_writer->xmlEndTag("qtimetadatafield");
		$a_xml_writer->xmlStartTag("qtimetadatafield");
		$a_xml_writer->xmlElement("fieldlabel", NULL, "AUTHOR");
		$a_xml_writer->xmlElement("fieldentry", NULL, $this->getAuthor());
		$a_xml_writer->xmlEndTag("qtimetadatafield");
		$a_xml_writer->xmlEndTag("qtimetadata");
		$a_xml_writer->xmlEndTag("itemmetadata");
		
		// PART I: qti presentation
		$attrs = array(
			"label" => $this->getTitle()
		);
		$a_xml_writer->xmlStartTag("presentation", $attrs);
		// add flow to presentation
		$a_xml_writer->xmlStartTag("flow");
		// add material with question text to presentation
		$a_xml_writer->xmlStartTag("material");
		$a_xml_writer->xmlElement("mattext", NULL, $this->getQuestion());
		$a_xml_writer->xmlEndTag("material");
		// add answers to presentation
		$attrs = array(
			"ident" => "IM",
			"rcardinality" => "Single"
		);
		$a_xml_writer->xmlStartTag("response_xy", $attrs);
		$solution = $this->getSuggestedSolution(0);
		if (count($solution))
		{
			if (preg_match("/il_(\d*?)_(\w+)_(\d+)/", $solution["internal_link"], $matches))
			{
				$a_xml_writer->xmlStartTag("material");
				$intlink = "il_" . IL_INST_ID . "_" . $matches[2] . "_" . $matches[3];
				if (strcmp($matches[1], "") != 0)
				{
					$intlink = $solution["internal_link"];
				}
				$attrs = array(
					"label" => "suggested_solution"
				);
				$a_xml_writer->xmlElement("mattext", $attrs, $intlink);
				$a_xml_writer->xmlEndTag("material");
			}
		}
		$a_xml_writer->xmlStartTag("render_hotspot");
		$a_xml_writer->xmlStartTag("material");
		$imagetype = "image/jpeg";
		if (preg_match("/.*\.(png|gif)$/", $this->get_image_filename(), $matches))
		{
			$imagetype = "image/" . $matches[1];
		}
		$attrs = array(
			"imagtype" => $imagetype,
			"label" => $this->get_image_filename()
		);
		if ($a_include_binary)
		{
			if ($force_image_references)
			{
				$attrs["uri"] = $this->getImagePathWeb() . $this->get_image_filename();
				$a_xml_writer->xmlElement("matimage", $attrs);
			}
			else
			{
				$attrs["embedded"] = "base64";
				$imagepath = $this->getImagePath() . $this->get_image_filename();
				$fh = fopen($imagepath, "rb");
				if ($fh == false)
				{
					global $ilErr;
					$ilErr->raiseError($this->lng->txt("error_open_image_file"), $ilErr->MESSAGE);
					return;
				}
				$imagefile = fread($fh, filesize($imagepath));
				fclose($fh);
				$base64 = base64_encode($imagefile);
				$a_xml_writer->xmlElement("matimage", $attrs, $base64, FALSE, FALSE);
			}
		}
		else
		{
			$a_xml_writer->xmlElement("matimage", $attrs);
		}
		$a_xml_writer->xmlEndTag("material");

		// add answers
		foreach ($this->answers as $index => $answer)
		{
			$rared = "";
			switch ($answer->getArea())
			{
				case "rect":
					$rarea = "Rectangle";
					break;
				case "circle":
					$rarea = "Ellipse";
					break;
				case "poly":
					$rarea = "Bounded";
					break;
			}
			$attrs = array(
				"ident" => $index,
				"rarea" => $rarea
			);
			$a_xml_writer->xmlStartTag("response_label", $attrs);
			$a_xml_writer->xmlData($answer->getCoords());
			$a_xml_writer->xmlStartTag("material");
			$a_xml_writer->xmlElement("mattext", NULL, $answer->getAnswertext());
			$a_xml_writer->xmlEndTag("material");
			$a_xml_writer->xmlEndTag("response_label");
		}
		$a_xml_writer->xmlEndTag("render_hotspot");
		$a_xml_writer->xmlEndTag("response_xy");
		$a_xml_writer->xmlEndTag("flow");
		$a_xml_writer->xmlEndTag("presentation");

		// PART II: qti resprocessing
		$a_xml_writer->xmlStartTag("resprocessing");
		$a_xml_writer->xmlStartTag("outcomes");
		$a_xml_writer->xmlStartTag("decvar");
		$a_xml_writer->xmlEndTag("decvar");
		$a_xml_writer->xmlEndTag("outcomes");
		// add response conditions
		foreach ($this->answers as $index => $answer)
		{
			$attrs = array(
				"continue" => "Yes"
			);
			$a_xml_writer->xmlStartTag("respcondition", $attrs);
			// qti conditionvar
			$a_xml_writer->xmlStartTag("conditionvar");
			if (!$answer->isStateSet())
			{
				$a_xml_writer->xmlStartTag("not");
			}
			$areatype = "";
			switch ($answer->getArea())
			{
				case "rect":
					$areatype = "Rectangle";
					break;
				case "circle":
					$areatype = "Ellipse";
					break;
				case "poly":
					$areatype = "Bounded";
					break;
			}
			$attrs = array(
				"respident" => "IM",
				"areatype" => $areatype
			);
			$a_xml_writer->xmlElement("varinside", $attrs, $answer->getCoords());
			if (!$answer->isStateSet())
			{
				$a_xml_writer->xmlEndTag("not");
			}
			$a_xml_writer->xmlEndTag("conditionvar");
			// qti setvar
			$attrs = array(
				"action" => "Add"
			);
			$a_xml_writer->xmlElement("setvar", $attrs, $answer->getPoints());
			$linkrefid = "response_$index";
			$attrs = array(
				"feedbacktype" => "Response",
				"linkrefid" => $linkrefid
			);
			$a_xml_writer->xmlElement("displayfeedback", $attrs);
			$a_xml_writer->xmlEndTag("respcondition");
		}
		$a_xml_writer->xmlEndTag("resprocessing");

		// PART III: qti itemfeedback
		foreach ($this->answers as $index => $answer)
		{
			$linkrefid = "";
			$linkrefid = "response_$index";
			$attrs = array(
				"ident" => $linkrefid,
				"view" => "All"
			);
			$a_xml_writer->xmlStartTag("itemfeedback", $attrs);
			// qti flow_mat
			$a_xml_writer->xmlStartTag("flow_mat");
			$a_xml_writer->xmlStartTag("material");
			$a_xml_writer->xmlElement("mattext");
			$a_xml_writer->xmlEndTag("material");
			$a_xml_writer->xmlEndTag("flow_mat");
			$a_xml_writer->xmlEndTag("itemfeedback");
		}
		
		$a_xml_writer->xmlEndTag("item");
		$a_xml_writer->xmlEndTag("questestinterop");

		$xml = $a_xml_writer->xmlDumpMem(FALSE);
		if (!$a_include_header)
		{
			$pos = strpos($xml, "?>");
			$xml = substr($xml, $pos + 2);
		}
		return $xml;
	}

/**
* Gets the imagemap question
*
* Gets the question string of the assImagemapQuestion object
*
* @return string The question string of the assImagemapQuestion object
* @access public
* @see $question
*/
  function getQuestion() {
    return $this->question;
  }

/**
* Sets the imagemap question
*
* Sets the question string of the assImagemapQuestion object
*
* @param string $question A string containing the imagemap question
* @access public
* @see $question
*/
  function setQuestion($question = "") {
    $this->question = $question;
  }

/**
* Gets the imagemap file name
*
* Gets the imagemap file name
*
* @return string The imagemap file of the assImagemapQuestion object
* @access public
* @see $imagemap_filename
*/
  function get_imagemap_filename() {
    return $this->imagemap_filename;
  }

/**
* Sets the imagemap file name
*
* Sets the imagemap file name
*
* @param string $imagemap_file.
* @access public
* @see $imagemap_filename
*/
  function setImagemapFilename($imagemap_filename, $imagemap_tempfilename = "") {
    if (!empty($imagemap_filename)) {
      $this->imagemap_filename = $imagemap_filename;
    }
    if (!empty($imagemap_tempfilename)) {
 	    $fp = fopen($imagemap_tempfilename, "r");
 	    $contents = fread($fp, filesize($imagemap_tempfilename));
      fclose($fp);
			if (preg_match_all("/<area(.+)>/siU", $contents, $matches)) {
		  	for ($i=0; $i< count($matches[1]); $i++) {
		    	preg_match("/alt\s*=\s*\"(.+)\"\s*/siU", $matches[1][$i], $alt);
		    	preg_match("/coords\s*=\s*\"(.+)\"\s*/siU", $matches[1][$i], $coords);
		    	preg_match("/shape\s*=\s*\"(.+)\"\s*/siU", $matches[1][$i], $shape);
					$this->addAnswer($alt[1], 0.0, count($this->answers), $coords[1], $shape[1]);
		  	}
			}
    }
	}

/**
* Gets the image file name
*
* Gets the image file name
*
* @return string The image file name of the assImagemapQuestion object
* @access public
* @see $image_filename
*/
  function get_image_filename() {
    return $this->image_filename;
  }

/**
* Sets the image file name
*
* Sets the image file name
*
* @param string $image_file name.
* @access public
* @see $image_filename
*/
  function setImageFilename($image_filename, $image_tempfilename = "") {

    if (!empty($image_filename)) 
		{
			$image_filename = str_replace(" ", "_", $image_filename);
      $this->image_filename = $image_filename;
    }
		if (!empty($image_tempfilename)) {
			$imagepath = $this->getImagePath();
			if (!file_exists($imagepath)) {
				ilUtil::makeDirParents($imagepath);
			}
			
			if (!ilUtil::moveUploadedFile($image_tempfilename, $image_filename, $imagepath.$image_filename))
			{
				$this->ilias->raiseError("The image could not be uploaded!", $this->ilias->error_obj->MESSAGE);
			}
		}
  }

/**
* Gets the imagemap file contents
*
* Gets the imagemap file contents
*
* @return string The imagemap file contents of the assImagemapQuestion object
* @access public
* @see $imagemap_contents
*/
  function get_imagemap_contents($href = "#") {
		$imagemap_contents = "<map name=\"".$this->title."\"> ";
		for ($i = 0; $i < count($this->answers); $i++) {
	 		$imagemap_contents .= "<area alt=\"".$this->answers[$i]->getAnswertext()."\" ";
	 		$imagemap_contents .= "shape=\"".$this->answers[$i]->getArea()."\" ";
	 		$imagemap_contents .= "coords=\"".$this->answers[$i]->getCoords()."\" ";
	 		$imagemap_contents .= "href=\"$href&selimage=" . $this->answers[$i]->getOrder() . "\" /> ";
		}
		$imagemap_contents .= "</map>";
    return $imagemap_contents;
  }

/**
* Adds a possible answer for a imagemap question
*
* Adds a possible answer for a imagemap question. A ASS_AnswerImagemap object will be
* created and assigned to the array $this->answers.
*
* @param string $answertext The answer text
* @param double $points The points for selecting the answer (even negative points can be used)
* @param integer $status The state of the answer (set = 1 or unset = 0)
* @param integer $order A possible display order of the answer
* @access public
* @see $answers
* @see ASS_AnswerImagemap
*/
  function addAnswer(
    $answertext = "",
    $points = 0.0,
    $order = 0,
    $coords="",
    $area=""
  )
  {
		include_once "./assessment/classes/class.assAnswerImagemap.php";
    if (array_key_exists($order, $this->answers)) 
		{
      // Insert answer
      $answer = new ASS_AnswerImagemap($answertext, $points, $order, $coords, $area);
			for ($i = count($this->answers) - 1; $i >= $order; $i--) 
			{
				$this->answers[$i+1] = $this->answers[$i];
				$this->answers[$i+1]->setOrder($i+1);
			}
			$this->answers[$order] = $answer;
    }
		else 
		{
      // Append answer
      $answer = new ASS_AnswerImagemap($answertext, $points, count($this->answers), $coords, $area);
      array_push($this->answers, $answer);
    }
  }

/**
* Returns the number of answers
*
* Returns the number of answers
*
* @return integer The number of answers of the multiple choice question
* @access public
* @see $answers
*/
  function getAnswerCount() {
    return count($this->answers);
  }

/**
* Returns an answer
*
* Returns an answer with a given index. The index of the first
* answer is 0, the index of the second answer is 1 and so on.
*
* @param integer $index A nonnegative index of the n-th answer
* @return object ASS_AnswerImagemap-Object containing the answer
* @access public
* @see $answers
*/
  function getAnswer($index = 0) {
    if ($index < 0) return NULL;
    if (count($this->answers) < 1) return NULL;
    if ($index >= count($this->answers)) return NULL;
    return $this->answers[$index];
  }

/**
* Deletes an answer
*
* Deletes an area with a given index. The index of the first
* area is 0, the index of the second area is 1 and so on.
*
* @param integer $index A nonnegative index of the n-th answer
* @access public
* @see $answers
*/
  function deleteArea($index = 0) {
    if ($index < 0) return;
    if (count($this->answers) < 1) return;
    if ($index >= count($this->answers)) return;
    unset($this->answers[$index]);
    $this->answers = array_values($this->answers);
    for ($i = 0; $i < count($this->answers); $i++) {
      if ($this->answers[$i]->getOrder() > $index) {
        $this->answers[$i]->setOrder($i);
      }
    }
  }

/**
* Deletes all answers
*
* Deletes all answers
*
* @access public
* @see $answers
*/
  function flushAnswers() {
    $this->answers = array();
  }

/**
* Returns the maximum points, a learner can reach answering the question
*
* Returns the maximum points, a learner can reach answering the question
*
* @access public
* @see $points
*/
  function getMaximumPoints() {
		$points = 0;
		foreach ($this->answers as $key => $value) {
			if ($value->getPoints() > $points)
			{
				$points = $value->getPoints();
			}
		}
		return $points;
  }

	/**
	* Returns the points, a learner has reached answering the question
	*
	* Returns the points, a learner has reached answering the question
	* The points are calculated from the given answers including checks
	* for all special scoring options in the test container.
	*
	* @param integer $user_id The database ID of the learner
	* @param integer $test_id The database Id of the test containing the question
	* @access public
	*/
	function calculateReachedPoints($active_id, $pass = NULL)
	{
		global $ilDB;
		
    $found_values = array();
		if (is_null($pass))
		{
			$pass = $this->getSolutionMaxPass($active_id);
		}
		$query = sprintf("SELECT * FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s",
			$ilDB->quote($active_id . ""),
			$ilDB->quote($this->getId() . ""),
			$ilDB->quote($pass . "")
		);
    $result = $ilDB->query($query);
		while ($data = $result->fetchRow(DB_FETCHMODE_OBJECT))
		{
			if (strcmp($data->value1, "") != 0)
			{
				array_push($found_values, $data->value1);
			}
		}
		$points = 0;
		if (count($found_values) > 0)
		{
			foreach ($this->answers as $key => $answer)
			{
				if (in_array($key, $found_values))
				{
					$points += $answer->getPoints();
				}
			}
		}

		$points = parent::calculateReachedPoints($active_id, $pass = NULL, $points);
		return $points;
	}

/**
* Saves the learners input of the question to the database
*
* Saves the learners input of the question to the database
*
* @param integer $test_id The database id of the test containing this question
* @return boolean Indicates the save status (true if saved successful, false otherwise)
* @access public
* @see $answers
*/
  function saveWorkingData($active_id, $pass = NULL) 
	{
    global $ilDB;
		global $ilUser;
    $db =& $ilDB->db;

		include_once "./assessment/classes/class.ilObjTest.php";
		$activepass = ilObjTest::_getPass($active_id);
		
    $query = sprintf("DELETE FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s",
			$db->quote($active_id . ""),
			$db->quote($this->getId() . ""),
			$db->quote($activepass . "")
    );
    $result = $db->query($query);

		if (strlen($_GET["selImage"]))
		{
			$query = sprintf("INSERT INTO tst_solutions (solution_id, active_fi, question_fi, value1, value2, pass, TIMESTAMP) VALUES (NULL, %s, %s, %s, NULL, %s, NULL)",
				$db->quote($active_id),
				$db->quote($this->getId()),
				$db->quote($_GET["selImage"]),
				$db->quote($activepass . "")
			);
			$result = $db->query($query);

			include_once ("./classes/class.ilObjAssessmentFolder.php");
			if (ilObjAssessmentFolder::_enabledAssessmentLogging())
			{
				$this->logAction($this->lng->txtlng("assessment", "log_user_entered_values", ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
			}
		}
		else
		{
			include_once ("./classes/class.ilObjAssessmentFolder.php");
			if (ilObjAssessmentFolder::_enabledAssessmentLogging())
			{
				$this->logAction($this->lng->txtlng("assessment", "log_user_not_entered_values", ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
			}
		}

    parent::saveWorkingData($active_id, $pass);
		return true;
  }

	function syncWithOriginal()
	{
		global $ilias;
		if ($this->original_id)
		{
			$complete = 0;
			if ($this->isComplete())
			{
				$complete = 1;
			}
			$db = & $ilias->db;
	
			$estw_time = $this->getEstimatedWorkingTime();
			$estw_time = sprintf("%02d:%02d:%02d", $estw_time['h'], $estw_time['m'], $estw_time['s']);
	
			$query = sprintf("UPDATE qpl_questions SET obj_fi = %s, title = %s, comment = %s, author = %s, question_text = %s, working_time = %s, points = %s, image_file = %s, complete = %s WHERE question_id = %s",
				$db->quote($this->obj_id. ""),
				$db->quote($this->title . ""),
				$db->quote($this->comment . ""),
				$db->quote($this->author . ""),
				$db->quote($this->question . ""),
				$db->quote($estw_time . ""),
				$db->quote($this->getMaximumPoints() . ""),
				$db->quote($this->image_filename . ""),
				$db->quote($complete . ""),
				$db->quote($this->original_id . "")
			);
			$result = $db->query($query);
			$query = sprintf("UPDATE qpl_question_imagemap SET image_file = %s WHERE question_fi = %s",
				$db->quote($this->image_filename . ""),
				$db->quote($this->original_id . "")
			);
			$result = $ilDB->query($query);

			if ($result == DB_OK)
			{
				// write answers
				// delete old answers
				$query = sprintf("DELETE FROM qpl_answer_imagemap WHERE question_fi = %s",
					$db->quote($this->original_id)
				);
				$result = $db->query($query);
	
				foreach ($this->answers as $key => $value)
				{
					$answer_obj = $this->answers[$key];
					$query = sprintf("INSERT INTO qpl_answer_imagemap (answer_id, question_fi, answertext, points, aorder, correctness, coords, area) VALUES (NULL, %s, %s, %s, %s, %s, %s, %s)",
						$db->quote($this->original_id . ""),
						$db->quote($answer_obj->getAnswertext() . ""),
						$db->quote($answer_obj->getPoints() . ""),
						$db->quote($answer_obj->getOrder() . ""),
						$db->quote($answer_obj->getState() . ""),
						$db->quote($answer_obj->getCoords() . ""),
						$db->quote($answer_obj->getArea() . "")
						);
					$answer_result = $db->query($query);
				}
			}
			parent::syncWithOriginal();
		}
	}

	/**
	* Returns the question type of the question
	*
	* Returns the question type of the question
	*
	* @return integer The question type of the question
	* @access public
	*/
	function getQuestionType()
	{
		return 6;
	}

	/**
	* Returns the name of the additional question data table in the database
	*
	* Returns the name of the additional question data table in the database
	*
	* @return string The additional table name
	* @access public
	*/
	function getAdditionalTableName()
	{
		return "qpl_question_imagemap";
	}

	/**
	* Returns the name of the answer table in the database
	*
	* Returns the name of the answer table in the database
	*
	* @return string The answer table name
	* @access public
	*/
	function getAnswerTableName()
	{
		return "qpl_answer_imagemap";
	}
}

?>
