<?php

require 'connect.php';
require 'suggestion.class.php';
require 'Akismet.class.php';

// If the request did not come from AJAX, exit:
if ($_SERVER['HTTP_X_REQUESTED_WITH'] !='XMLHttpRequest') {
	exit;
}

// Converting the IP to a number. This is a more effective way
// to store it in the database:
$ip	= sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']));

if ($_GET['action'] == 'vote') {
	$v = (int) $_GET['vote'];
	$id = (int) $_GET['id'];

	if ($v != -1 && $v != 1) {
		exit;
	}

	// Checking to see whether such a suggest item id exists:	
	if (!$mysqli->query("
			SELECT 1
			FROM `suggestions`
			WHERE 1=1
				AND `id` = $id")->num_rows) {
		exit;
	}
	
	// The id, ip and day fields are set as a primary key.
	// The query will fail if we try to insert a duplicate key,
	// which means that a visitor can vote only once per day.
	$mysqli->query("
		INSERT INTO `suggestions_votes` (
			`suggestion_id`,
			`ip`,
			`day`,
			`vote`
		) VALUES (
			$id,
			$ip,
			CURRENT_DATE,
			$v
		)
	");

	if ($mysqli->affected_rows == 1) {
		$mysqli->query("
			UPDATE `suggestions` SET 
				".($v == 1 ? '`votes_up` = `votes_up` + 1' : '`votes_down` = `votes_down` + 1').",
				`rating` = `rating` + $v
			WHERE 1=1
				AND `id` = $id
		");
	}
} elseif ($_GET['action'] == 'submit') {
	$akismet = new Akismet($MyURL ,$WordPressAPIKey);
	$akismet->setCommentContent($_GET['content']);
	
	if($akismet->isCommentSpam()){
		echo json_encode(array(
			'html'	=> '<li><div class="text">Your suggestion looked like SPAM. Please try again!</div></li>'
		));
		die;
	}

	if (get_magic_quotes_gpc()) {
		array_walk_recursive($_GET, create_function('&$v,$k','$v = stripslashes($v);'));
	}

	// Stripping the content	
	$_GET['content'] = htmlspecialchars(strip_tags($_GET['content']));
	
	if (mb_strlen($_GET['content'], 'utf-8') < 3) {
		exit;
	}
	
	$mysqli->query("
		INSERT INTO `suggestions`
		SET `suggestion` = '".$mysqli->real_escape_string($_GET['content'])."'");
	
	// Outputting the HTML of the newly created suggestion in a JSON format.
	// We are using (string) to trigger the magic __toString() method of the object.
	echo json_encode(array(
		'html'	=> (string) (new Suggestion(array(
			'id'			=> $mysqli->insert_id,
			'suggestion'	=> $_GET['content']
		)))
	));
}
