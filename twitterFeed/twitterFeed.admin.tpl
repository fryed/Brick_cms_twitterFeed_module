<section class="mainCol col">

	<h1>Module: {$page.title}</h1>
	<hr/>
	
	<ul id="tabs">
		<li><a href="#module">Module</a></li>
	</ul>
	
	<div id="module">

		<form method="post" action="">
			
			<fieldset>
				
				<label>Username:</label>
				<input type="text" name="username" value="{$module.twitterFeed.settings.username}" required="required" placeholder="username"/>
				<br class="clearBoth"/>
				
				<label>Limit:</label>
				<input type="number" name="tweet_limit" value="{$module.twitterFeed.settings.tweet_limit}" required="required" placeholder="limit"/>
				<br class="clearBoth"/>

				<input type="submit" name="save_twitterFeed" value="save twitterFeed module"/>
				
			</fieldset>
			
		</form>
		
	</div>
	
</section>

