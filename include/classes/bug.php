<?php

class Bug implements ArrayAccess
{
	private $data;
	private $db;
	private $id;

	public function __construct($bugID, $db)
	{
		$this->db = $db;
		$this->id = $bugID == 'preview' ? 'preview' : intval($bugID);

		// Preview has its own faked set of data
		if ($this->isPreview()) {
			$this->data = $this->setPreviewData();
		} else {
			$this->data = $this->fetchData();
		}
	}

	public function offsetExists($key)
	{
		return isset($this->data[$key]);
	}

	public function offsetGet($key)
	{
		return $this->data[$key];
	}

	public function offsetSet($key, $value)
	{
		$this->data[$key] = $value;
		return true;
	}

	public function offsetUnset($key)
	{
		unset($this->data[$key]);
		return true;
	}

	public function getComments()
	{
		$query = "
			SELECT c.id, c.email, c.comment, c.comment_type,
				UNIX_TIMESTAMP(c.ts) AS added,
				c.reporter_name AS comment_name
			FROM bugdb_comments c
			WHERE c.bug = ?
			GROUP BY c.id ORDER BY c.ts
		";
		return $this->db->prepare($query)
			->execute([$this->id])
			->fetchAll(MDB2_FETCHMODE_ASSOC);
	}

	public function exists()
	{
		return !empty($this->data);
	}

	public function isPreview()
	{
		return $this->id === 'preview';
	}

	private function fetchData()
	{
		$query = 'SELECT b.id, b.package_name, b.bug_type, b.email, b.reporter_name,
			b.sdesc, b.ldesc, b.php_version, b.php_os,
			b.status, b.ts1, b.ts2, b.assign, b.block_user_comment,
			b.private, b.cve_id,
			UNIX_TIMESTAMP(b.ts1) AS submitted,
			UNIX_TIMESTAMP(b.ts2) AS modified,
			COUNT(bug=b.id) AS votes,
			IFNULL((SELECT z.project FROM bugdb_pseudo_packages z WHERE z.name = b.package_name LIMIT 1), "php") project,
			SUM(reproduced) AS reproduced, SUM(tried) AS tried,
			SUM(sameos) AS sameos, SUM(samever) AS samever,
			AVG(score)+3 AS average, STD(score) AS deviation
			FROM bugdb b
			LEFT JOIN bugdb_votes ON b.id = bug
			WHERE b.id = ?
			GROUP BY bug';

		return $this->db->prepare($query)
			->execute([$this->id])
			->fetchRow(MDB2_FETCHMODE_ASSOC);
	}

	private function setPreviewData()
	{
		// If session is empty set data to null thus marking bug as not found
		if ($_SESSION)
	}
}
