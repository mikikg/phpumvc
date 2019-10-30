<?php

//-------------------------------------------------------------
// Custom Micro MVC AD site
//
// Author: A. Markovic <mikikg@gmail.com>
//-------------------------------------------------------------

if (!defined('MyAdSite')) die ('Direct script access forbidden!');

class MyDB {

	private $auth = array(); //

	//Constructor
	//-------------------
	public function __construct ($base) {

		//open the database
		$this->db = new PDO('sqlite:'.$base.'/db/AdDb_PDO.sqlite');

		//UNCOMMENT TO RESET DB
		/*
		//INITIAL TABLES
		$this->db->exec("DROP TABLE user");
	    $this->db->exec("CREATE TABLE user (iduser INTEGER PRIMARY KEY, name TEXT, surname TEXT, email TEXT, tel TEXT, password TEXT, browser TEXT, ip TEXT, timestamp TEXT)");
		$this->db->exec("CREATE UNIQUE INDEX idx_emal ON user (email) ");
		$this->db->exec("CREATE INDEX idx_password ON user (password) ");
		$this->db->exec("CREATE INDEX idx_timestamp ON user (timestamp) ");
		$this->db->exec("INSERT INTO user (password, email) VALUES ('admin', 'admin@admin.com') ");

		$this->db->exec("DROP TABLE ad");
	    $this->db->exec("CREATE TABLE ad (idad INTEGER PRIMARY KEY, userid INTEGER, title TEXT, description TEXT, browser TEXT, ip TEXT, timestamp TEXT)");
	    $this->db->exec("CREATE INDEX idex_userid ON ad (userid) ");
	    $this->db->exec("INSERT INTO ad (userid, title, description) VALUES (1, 'Some Ad Title', 'Some Ad description') ");
	    //print_r($this->db->errorInfo());

		print "<table border=1>";
		$result = $this->db->query('SELECT * FROM user');
	    foreach($result as $row)
	    {
	      print "<tr>";
	      for ($x = 0; $x < 9; $x++) print "<td>&nbsp;".$row[$x]."</td>";
	      print "</tr>";
	    }
	    print "</table>";

		print "<table border=1>";
		$result = $this->db->query('SELECT * FROM ad');
	    foreach($result as $row)
	    {
	      print "<tr>";
	      for ($x = 0; $x < 7; $x++) print "<td>&nbsp;".$row[$x]."</td>";
	      print "</tr>";
	    }
	    print "</table>";
		*/

	}

	public function email_exists ($email) {
		$prep = $this->db->prepare('SELECT COUNT(iduser) AS cnt FROM user WHERE email = ?');
		$prep->execute(array(strtolower($email)));
		$result = $prep->fetch();
		return $result[0][0];
	}

	public function get_user_auth ($email, $pass) {
		$prep = $this->db->prepare('SELECT * FROM user WHERE email = ? AND password = ? LIMIT 1');
		$prep->execute(array(strtolower($email), $pass));
		$result = $prep->fetchAll();
		$_SESSION['user_data'] = $result[0];
		return $result[0];
	}

	public function insert_new_user ($data) {
		$prep = $this->db->prepare ("INSERT INTO user (name, surname, email, tel, password, browser, ip, timestamp) VALUES (?,?,?,?,?,?,?,?) ");
		$prep->execute(array($data['name'], $data['surname'], strtolower($data['email']), $data['tel'], $data['password1'], $_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR'], time()));
	}

	public function get_last_cards ($count, $userid=0) {

		if ($userid == 0) {
			$prep = $this->db->prepare ("SELECT * FROM ad ORDER BY timestamp DESC LIMIT ? ");
			$prep->execute(array($count));
		} else {
			$prep = $this->db->prepare ("SELECT * FROM ad WHERE userid = ? ORDER BY timestamp DESC LIMIT ? ");
			$prep->execute(array($userid, $count));
		}

		$data = array();
		while ($row = $prep->fetch()) {
		    $data[$row['idad']] = $row;
		}

	    return $data;
	}

	public function insert_ad ($data, $userid) {
		$prep = $this->db->prepare ('INSERT INTO ad (userid, title, description, browser, ip, timestamp) VALUES (?,?,?,?,?,?) ');
		$prep->execute(array($userid, $data['title'], $data['description'], $_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR'], time()));
		return $this->db->lastInsertId();
	}

	public function get_ad_details($id) {
		$prep = $this->db->prepare('SELECT * FROM ad LEFT JOIN user ON(ad.userid = user.iduser) WHERE idad = ? LIMIT 1');
		$prep->execute(array($id));
		$result = $prep->fetchAll();

		if (isset($result[0])) {
			return $result[0];
		} else {
			return null;
		}
	}

}
