<?php

class i373i7i
{
	/**
     * Amount of queries made
     *
     * @access private
     * @var int
     */
	public $num_queries = 0;
	/**
     * MySQL result, which is either a resource or boolean.
     *
     * @access protected
     * @var mixed
     */
	public $result;
	/**
     * Last query made
     *
     * @access private
     * @var array
     */
	public $last_query;
	/**
     * Database Username
     *
     * @access protected
     * @var string
     */
	protected $dbuser;
	/**
     * Database Password
     *
     * @access protected
     * @var string
     */
	protected $dbpassword;
	/**
     * Database Name
     *
     * @access protected
     * @var string
     */
	protected $dbname;
	/**
     * Database Host
     *
     * @access protected
     * @var string
     */
	protected $dbhost;
	/**
     * Database Handle
     *
     * @access protected
     * @var string
     */
	public $dbh;

	public function __construct($dbuser, $dbpassword, $dbname, $dbhost)
	{
		$this->dbuser = $dbuser;
		$this->dbpassword = $dbpassword;
		$this->dbname = $dbname;
		$this->dbhost = $dbhost;
		$this->db_connect();
	}

	public function close_mysql()
	{
		mysqli_close($this->dbh);
		return true;
	}

	public function db_connect()
	{
		$this->dbh = mysqli_connect($this->dbhost, $this->dbuser, $this->dbpassword, $this->dbname, 7999);

		if (!$this->dbh) {
			exit("Connect Error: " . mysqli_connect_error());
		}

		return true;
	}

	public function query($query, $buffered = false)
	{
		if ($this->dbh) {
			$numargs = func_num_args();
			$arg_list = func_get_args();
			$next_arg_list = array();

			for ($i = 1; $i < $numargs; $i++) {
				$next_arg_list[] = mysqli_real_escape_string($this->dbh, $arg_list[$i]);
			}

			$query = vsprintf($query, $next_arg_list);
			$this->last_query = $query;

			if ($buffered === true) {
				$this->result = mysqli_query($this->dbh, $query, MYSQLI_USE_RESULT);
			}
			else {
				$this->result = mysqli_query($this->dbh, $query);
			}

			if (!$this->result) {
				ipTV_lib::SaveLog("MySQL Query Failed [" . $query . "]: " . mysqli_error($this->dbh));
			}

			->num_queries++;
		}
	}

	public function get_rows($use_id = false, $column_as_id = "", $unique_row = true)
	{
		if ($this->dbh && $this->result) {
			$rows = array();

			if (0 < $this->num_rows()) {
				while ($row = mysqli_fetch_array($this->result, MYSQLI_ASSOC)) {
					if ($use_id && array_key_exists($column_as_id, $row)) {
						if (!isset($rows[$row[$column_as_id]])) {
							$rows[$row[$column_as_id]] = array();
						}

						if (!$unique_row) {
							$rows[$row[$column_as_id]][] = $row;
						}
						else {
							$rows[$row[$column_as_id]] = $row;
						}
					}
					else {
						$rows[] = $row;
					}
				}
			}

			return $rows;
		}

		return false;
	}

	public function get_row()
	{
		if ($this->dbh && $this->result) {
			$row = array();

			if (0 < $this->num_rows()) {
				$row = mysqli_fetch_array($this->result, MYSQLI_ASSOC);
			}

			return $row;
		}

		return false;
	}

	public function get_col()
	{
		if ($this->dbh && $this->result) {
			$row = false;

			if (0 < $this->num_rows()) {
				$row = mysqli_fetch_array($this->result, MYSQLI_NUM);
				$row = $row[0];
			}

			return $row;
		}

		return false;
	}

	public function affected_rows()
	{
		$mysqli_affected_rows = mysqli_affected_rows($this->dbh);
		return empty($mysqli_affected_rows) ? 0 : $mysqli_affected_rows;
	}

	public function simple_query($query)
	{
		$this->result = mysqli_query($this->dbh, $query);

		if (!$this->result) {
			ipTV_lib::SaveLog("MySQL Query Failed [" . $query . "]: " . mysqli_error($this->dbh));
		}
	}

	public function escape($string)
	{
		return mysqli_real_escape_string($this->dbh, $string);
	}

	public function num_fields()
	{
		$mysqli_num_fields = mysqli_num_fields($this->result);
		return empty($mysqli_num_fields) ? 0 : $mysqli_num_fields;
	}

	public function last_insert_id()
	{
		$mysql_insert_id = mysqli_insert_id($this->dbh);
		return empty($mysql_insert_id) ? 0 : $mysql_insert_id;
	}

	public function num_rows()
	{
		$mysqli_num_rows = mysqli_num_rows($this->result);
		return empty($mysqli_num_rows) ? 0 : $mysqli_num_rows;
	}
}


?>
