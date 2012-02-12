<?php
/* 
EXAMPLES OF HOW TO CONNECT TO THE DATABASE
all db functions use mysql queries. 
eg $rows and $params will be somthing like: 
 
$params = "WHERE id = 1"
$rows	= "row_name varchar(255)"
$values = "'val1','val2','val3'" 

google php mysql queries to learn more
  
CREATE TABLE
DBconnect::create($table,$rows);
each table will automatically have the id row added and set as primary.
 
ADD ROW TO TABLE
DBconnect::insert($table,$rows,$values); 
 
UPDATE ROW IN TABLE
DBconnect::update($table,$row,$value,$params);
 
DELETE ROW FROM TABLE
DBconnect::delete($table,$row,$value);

DROP TABLE FROM DB
DBconnect::drop($table);
 
QUERY TABLE
DBconnect::query($row,$table,$params);
this will bring back an array is result is an array or string if not.

QUERY TABLE ARRAY
DBconnect::queryArray($row,$table,$params);
this will always produce an array even if there is only 1 result. 
*/

//-----twitterFeed MODULE-----//

class twitterFeed extends DBconnect {
		
	//THE VARS
	//these vars can be assessed anywhere in
	//the module by using $this->page["name"], or 
	//$this->site["keywords"] etc.
	var $module;
	var $site;
	var $menu;
	var $page;
	var $blog;
	var $news;
	var $brick;
	var $settings;
	
	//SETUP FUNCTION
	//this function is automatically called
	//depending on wether the module is already installed.
	//you should create all your tables within this function.
	public function setupModule(){
			
		//create the twitter feed settings table
		$rows = "
			username varchar(30) not null,
			tweet_limit int(11) not null default '1',
			profile_pic varchar(100) not null,
			followers int not null default '0',
			last_updated int(111) not null default '0'
		";
		$success = DBconnect::create("twitterfeed_settings",$rows);
		
		//create the twitter feed table
		$rows = "
			content varchar(255) not null,
			date varchar(30) not null,
			reply_link varchar(100) not null,
			retweet_link varchar(100) not null,
			favourite_link varchar(100) not null
		";			
		$success = DBconnect::create("twitterfeed_tweets",$rows);
		
		//insert a row into our table
		$rows 	= "username,tweet_limit";
		$values	= "'username here','3'";	
		DBconnect::insert("twitterfeed_settings",$rows,$values);
		
		//if the function returns true the module
		//will be installed and setup will not run
		//again. if not setup will run on every page load.
		if($success){
			return true;
		}	
		
	}
	
	//RUN FUNCTION
	//this is the core of the module which you should 
	//put all the main functionality. feel free to add functions
	//and call them from here.
	public function runModule(){
		
		//set the modual template var to only send the
		//module information to a specific template
		//by default the information will be global.
		//this->module["template"] = "page.tpl";
		
		//get the twittter settings info from the database to send to the page
		$twitterSettings 			= DBconnect::query("*","twitterfeed_settings","");
		$this->module["settings"]	= $twitterSettings;	
		
		//check if it has been a day since last update
		//if it has request tweets from twitter and store
		//in db else just use db tweets.
		$time = time();
		$diff = $twitterSettings["last_updated"]+86400;
		if($time > $diff){
			
			//its been a day - fetch new tweets
			$TWITTER 	= new twitter();
			$TWITTER->limit = $twitterSettings["tweet_limit"];
			$TWITTER->username = $twitterSettings["username"];
			$TWITTER->init();
			$tweets 	= $TWITTER->getTweets();
			$twitPic 	= $TWITTER->getPic();
			$followers 	= $TWITTER->getFollowers();
			
			//update twitter settings
			$params = "WHERE id = 1";
			DBconnect::update("twitterfeed_settings","profile_pic",$twitPic,$params);
			DBconnect::update("twitterfeed_settings","followers",$followers,$params);
			DBconnect::update("twitterfeed_settings","last_updated",$time,$params);
			
			//delete all old tweets
			DBconnect::deleteAll("twitterfeed_tweets");  
			
			//loop tweets and add to db
			foreach($tweets as $tweet){
				//insert a row into our table
				$rows 	= "content,date,reply_link,retweet_link,favourite_link";
				$values	= "'".$tweet["text"]."','".$tweet["date"]."','".$tweet["reply_link"]."','".$tweet["retweet_link"]."','".$tweet["favourite_link"]."'";	
				DBconnect::insert("twitterfeed_tweets",$rows,$values);
			}

		}
		
		//get the tweets from the database to send to the page
		$tweets 					= DBconnect::queryArray("*","twitterfeed_tweets","");
		$this->module["tweets"]		= $tweets;	

		//check for post of our save button and run
		//the edit module function. this name must be unique
		//to every module.
		if(isset($_POST["save_twitterFeed"])){
			$this->editModule();
		}
		
	}
	
	//EDIT MODULE
	//this is called when the user has clicked the save
	//module button set in twitterFeed.admin.tpl
	public function editModule(){
		
		//update our table with the new twitter settings
		$username 		= $_POST["username"];
		$limit 			= $_POST["tweet_limit"];
		$params			= "WHERE id = 1";
		DBconnect::update("twitterfeed_settings","username",$username,$params);
		DBconnect::update("twitterfeed_settings","tweet_limit",$limit,$params);
		DBconnect::update("twitterfeed_settings","last_updated",0,$params);
		
		//send message to user and exit
		$_SESSION["messages"][] = "Message: twitterFeed module updated successfully.";
		
		//send user to module page
		header("Location: ".$this->module["url"]);
		exit;
		
	}
	
	//RETURN FUNCTION
	//this is called by the system to collect any
	//info that you want to send to the page.
	//anything put into the module array will be sent
	//to the page in the format {$module.module_name.value}
	public function returnModule(){
		
		return $this->module;
		
	}
	
	//UNINSTALL MODULE
	//the function called when the user chooses 
	//to uninstall the module. you should drop all custom
	//database tables and tidy up here.
	public function uninstallModule(){
		
		//drop custom tables
		DBconnect::drop("twitterfeed_settings");
		DBconnect::drop("twitterfeed_tweets");
		
	}

}

//-----CUSTOM CLASS TO BRING IN TWEETS FROM TWITTER-----//

class twitter {
	
	var $limit;
	var $tweets;
	var $username;
	var $profilePic;
	var $followers;
	
	//init twitter reader
	function init(){
		
		//get feed
		$feed = "http://api.twitter.com/1/statuses/user_timeline.json?&screen_name=".$this->username."&count=".$this->limit;
		$feed = file_get_contents($feed);
		
		//decode json
		$feed = json_decode($feed);
		
		//get profile pic
		$this->profilePic = $feed[0]->user->profile_image_url;
		
		//get followers
		$this->followers = $feed[0]->user->followers_count;
		
		//define tweet array
		$this->tweets = array();
		
		//build tweets
		$i = 0;
		foreach($feed as $feedItem){
			$this->tweets[$i]["text"] 			= $feedItem->text;
			$this->tweets[$i]["date"] 			= $feedItem->created_at;
			$this->tweets[$i]["id"]				= $feedItem->id;
			$this->tweets[$i]["reply_link"]		= "http://twitter.com/intent/tweet?in_reply_to=".$feedItem->id;
			$this->tweets[$i]["retweet_link"]	= "http://twitter.com/intent/retweet?tweet_id=".$feedItem->id;
			$this->tweets[$i]["favourite_link"]		= "http://twitter.com/intent/favorite?tweet_id=".$feedItem->id;
			$i++;
		}

		return $this->tweets;
		
	}
	
	//get profile pic
	function getPic(){
		return $this->profilePic;
	}
	
	//get tweets
	function getTweets(){
		return $this->tweets;
	}
	
	//get followers
	function getFollowers(){
		return $this->followers;
	}
	
}

?>