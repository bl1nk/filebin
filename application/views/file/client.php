<h1>Client</h1>

<p>
	Development (git): <?php echo anchor("http://git.server-speed.net/users/flo/fb/"); ?><br />
	Latest release: <?php echo $client_link ? anchor($client_link) : "unknown"; ?><br />
	GPG sigs, older versions: <a href="<?php echo $client_link_dir; ?>"><?php echo $client_link_dir; ?></a>
</p>

<p>To authenticate add the following to your ~/.netrc:</p>

<pre>
machine <?php echo $domain; ?> login my_username password my_secret_password
</pre>

<p>
	If you are using fb-client &ge;1.2 you can
	<a href="<?php echo	site_url("user/apikeys"); ?>">create an API key</a>,
	save it in <code>~/.config/fb-client/apikey</code> and remove
	your password from <code>.netrc</code>. Please refer to <code>man
	1 fb</code> for further details.
</p>

<p>
	If you are using fb-client &ge;1.1 you can use
	<code>~/.config/fb-client/config</code> to upload to a different
	pastebin URL (https or you own installation). Please refer to
	<code>man 1 fb</code> for further details.
</p>

<h2>Linux</h2>
<p>
	Arch Linux: pacman -S fb-client<br />
	Debian: <?php echo anchor($client_link_deb); ?><br />
	Slackware: <?php echo anchor($client_link_slackware); ?>
</p>

<h2>OS X</h2>
<p>
	Get <a href="http://brew.sh">Homebrew</a> and run <code>brew install fb-client</code>.
</p>

<h1>Shell</h1>

<pre>
curl -n -F "file=@/home/user/foo" <?php echo site_url("file/do_upload"); ?>   (binary safe)
cat file | curl -n -F "file=@-;filename=stdin" <?php echo site_url("file/do_upload"); ?>   (binary safe)
</pre>

