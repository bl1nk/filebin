<?php
/*
 * Copyright 2009-2013 Florian "Bluewind" Pritz <bluewind@server-speed.net>
 *
 * Licensed under AGPLv3
 * (see COPYING for full license text)
 *
 */

class Mfile extends CI_Model {

	function __construct()
	{
		parent::__construct();
		$this->load->model("muser");
	}

	// Returns an unused ID
	function new_id($min = 3, $max = 6)
	{
		static $id_blacklist = NULL;

		if ($id_blacklist == NULL) {
			// This prevents people from being unable to access their uploads
			// because of URL rewriting
			$id_blacklist = scandir(FCPATH);
			$id_blacklist[] = "file";
			$id_blacklist[] = "user";
		}

		$max_tries = 100;

		for ($try = 0; $try < $max_tries; $try++) {
			$id = random_alphanum($min, $max);

			if ($this->id_exists($id) || in_array($id, $id_blacklist)) {
				continue;
			}

			return $id;
		}

		show_error("Failed to find unused ID after $max_tries tries.");
	}

	function id_exists($id)
	{
		if (!$id) {
			return false;
		}

		$sql = '
			SELECT id
			FROM `files`
			WHERE `id` = ?
			LIMIT 1';
		$query = $this->db->query($sql, array($id));

		if ($query->num_rows() == 1) {
			return true;
		} else {
			return false;
		}
	}

	public function stale_hash($hash)
	{
		return $this->unused_file($hash);
	}

	function get_filedata($id)
	{
		$sql = '
			SELECT hash, filename, mimetype, date, user, filesize
			FROM `files`
			WHERE `id` = ?
			LIMIT 1';
		$query = $this->db->query($sql, array($id));

		if ($query->num_rows() == 1) {
			$return = $query->result_array();
			return $return[0];
		} else {
			return false;
		}
	}

	// return the folder in which the file with $hash is stored
	function folder($hash) {
		return $this->config->item('upload_path').'/'.substr($hash, 0, 3);
	}

	// Returns the full path to the file with $hash
	function file($hash) {
		return $this->folder($hash).'/'.$hash;
	}

	// Return mimetype of file
	function mimetype($file) {
		$fileinfo = new finfo(FILEINFO_MIME_TYPE);
		$mimetype = $fileinfo->file($file);

		return $mimetype;
	}

	public function image_dimension($file)
	{
		list($width, $height) = getimagesize($file);

		return "${width}x${height}";
	}

	/*
	 * This returns a square thumbnail for the input image
	 * Source: http://salman-w.blogspot.co.at/2009/04/crop-to-fit-image-using-aspphp.html
	 */
	public function makeThumb($id, $size = 150, $target_type = null)
	{
		$filedata = $this->get_filedata($id);
		if (!$filedata) {
			return false;
		}

		$source_path = $this->file($filedata["hash"]);

		list($source_width, $source_height, $source_type) = getimagesize($source_path);

		if ($target_type === null) {
			$target_type = $source_type;
		}

		$target_width = $size;
		$target_height = $size;

		switch ($source_type) {
			case IMAGETYPE_GIF:
				$source_gdim = imagecreatefromgif($source_path);
				break;
			case IMAGETYPE_JPEG:
				$source_gdim = imagecreatefromjpeg($source_path);
				break;
			case IMAGETYPE_PNG:
				$source_gdim = imagecreatefrompng($source_path);
				break;
			default:
				show_error("Unsupported image type");
		}

		$source_aspect_ratio = $source_width / $source_height;
		$desired_aspect_ratio = $target_width / $target_height;

		if ($source_aspect_ratio > $desired_aspect_ratio) {
			// Triggered when source image is wider
			$temp_height = $target_height;
			$temp_width = round(($target_height * $source_aspect_ratio));
		} else {
			// Triggered otherwise (i.e. source image is similar or taller)
			$temp_width = $target_width;
			$temp_height = round(($target_width / $source_aspect_ratio));
		}

		/*
		 * Resize the image into a temporary GD image
		 */

		$temp_gdim = imagecreatetruecolor($temp_width, $temp_height);
		imagecopyresampled(
			$temp_gdim,
			$source_gdim,
			0, 0,
			0, 0,
			$temp_width, $temp_height,
			$source_width, $source_height
		);

		/*
		 * Copy cropped region from temporary image into the desired GD image
		 */

		$x0 = ($temp_width - $target_width) / 2;
		$y0 = ($temp_height - $target_height) / 2;
		$thumb = imagecreatetruecolor($target_width, $target_height);
		imagecopy(
			$thumb,
			$temp_gdim,
			0, 0,
			$x0, $y0,
			$target_width, $target_height
		);

		ob_start();
		switch ($target_type) {
			case IMAGETYPE_GIF:
				$ret = imagegif($thumb);
				break;
			case IMAGETYPE_JPEG:
				$ret = imagejpeg($thumb);
				break;
			case IMAGETYPE_PNG:
				$ret = imagepng($thumb);
				break;
			default:
				assert(0);
		}
		$result = ob_get_clean();

		if (!$ret) {
			show_error("Failed to create thumbnail");
		}

		imagedestroy($thumb);
		imagedestroy($temp_gdim);
		imagedestroy($source_gdim);

		return $result;
	}

	// Add a hash to the DB
	function add_file($hash, $id, $filename)
	{
		$userid = $this->muser->get_userid();

		$mimetype = $this->mimetype($this->file($hash));

		$filesize = filesize($this->file($hash));
		$query = $this->db->query('
			INSERT INTO `files` (`hash`, `id`, `filename`, `user`, `date`, `mimetype`, `filesize`)
			VALUES (?, ?, ?, ?, ?, ?, ?)',
			array($hash, $id, $filename, $userid, time(), $mimetype, $filesize));
	}

	function adopt($id)
	{
		$userid = $this->muser->get_userid();

		$this->db->query("
			UPDATE files
			SET user = ?
			WHERE id = ?
			", array($userid, $id));
	}

	// remove old/invalid/broken IDs
	function valid_id($id)
	{
		$filedata = $this->get_filedata($id);
		if (!$filedata) {
			return false;
		}
		$file = $this->file($filedata['hash']);

		if (!file_exists($file)) {
			if (isset($filedata["hash"])) {
				$this->db->query('DELETE FROM files WHERE hash = ?', array($filedata['hash']));
			}
			return false;
		}

		// 0 age disables age checks
		if ($this->config->item('upload_max_age') == 0) return true;

		// small files don't expire
		if (filesize($file) <= $this->config->item("small_upload_size")) {
			return true;
		}

		// files older than this should be removed
		$remove_before = (time()-$this->config->item('upload_max_age'));

		if ($filedata["date"] < $remove_before) {
			// if the file has been uploaded multiple times the mtime is the time
			// of the last upload
			if (filemtime($file) < $remove_before) {
				unlink($file);
				$this->db->query('DELETE FROM files WHERE hash = ?', array($filedata['hash']));
			} else {
				$this->db->query('DELETE FROM files WHERE id = ? LIMIT 1', array($id));
			}
			return false;
		}

		return true;
	}

	function get_timeout_string($id)
	{
		$filedata = $this->get_filedata($id);
		$file = $this->file($filedata["hash"]);

		if (filesize($file) > $this->config->item("small_upload_size")) {
			return date("r", $filedata["date"] + $this->config->item("upload_max_age"));
		} else {
			return "unknown";
		}
	}

	private function unused_file($hash)
	{
		$sql = '
			SELECT id
			FROM `files`
			WHERE `hash` = ?
			LIMIT 1';
		$query = $this->db->query($sql, array($hash));

		if ($query->num_rows() == 0) {
			return true;
		} else {
			return false;
		}
	}

	function delete_id($id)
	{
		$filedata = $this->get_filedata($id);
		$userid = $this->muser->get_userid();

		if (!$this->id_exists($id)) {
			return false;
		}

		$sql = '
			DELETE
			FROM `files`
			WHERE `id` = ?
			AND user = ?
			LIMIT 1';
		$this->db->query($sql, array($id, $userid));

		if ($this->id_exists($id))  {
			return false;
		}

		if ($this->unused_file($filedata['hash'])) {
			unlink($this->file($filedata['hash']));
			@rmdir($this->folder($filedata['hash']));
		}
		return true;
	}

	public function get_lexers() {
		$this->load->driver('cache', array('adapter' => $this->config->item("cache_backend")));
		if (! $lexers = $this->cache->get('lexers')) {
			$lexers = array();
			$last_desc = "";
			exec("python ".escapeshellarg(FCPATH."scripts/get_lexer_list.py"), $output);

			foreach ($output as $line) {
				list($name, $desc) = explode("|", $line);
				if ($desc == $last_desc) {
					continue;
				}
				$last_desc = $desc;
				$lexers[$name] = $desc;
			}
			$lexers["text"] = "Plain text";
			$this->cache->save('lexers', $lexers, 1800);
		}

		return $lexers;
	}

	public function should_highlight($type)
	{
		if ($this->mime2lexer($type)) return true;

		return false;
	}

	// Allow certain types to be highlight without doing it automatically
	public function can_highlight($type)
	{
		$typearray = array(
			'image/svg+xml',
		);
		if (in_array($type, $typearray)) return true;

		if ($this->mime2lexer($type)) return true;

		return false;
	}

	// Return the lexer that should be used for highlighting
	public function autodetect_lexer($type, $filename)
	{
		if (!$this->can_highlight($type)) {
			return false;
		}

		$lexer = $this->mime2lexer($type);

		// filename lexers overwrite mime type mappings
		$filename_lexer = $this->filename2lexer($filename);
		if ($filename_lexer) {
			return $filename_lexer;
		}

		return $lexer;
	}

	// Map MIME types to lexers needed for highlighting
	private function mime2lexer($type)
	{
		$typearray = array(
		'application/javascript' => 'javascript',
		'application/mbox' => 'text',
		'application/smil' => 'ocaml',
		'application/x-applix-spreadsheet' => 'actionscript',
		'application/x-awk' => 'awk',
		'application/x-desktop' => 'text',
		'application/x-fluid' => 'text',
		'application/x-genesis-rom' => 'text',
		'application/x-java' => 'java',
		'application/x-m4' => 'text',
		'application/xml-dtd' => "xml",
		'application/xml' => 'xml',
		'application/x-perl' => 'perl',
		'application/x-php' => 'php',
		'application/x-ruby' => 'ruby',
		'application/x-shellscript' => 'bash',
		'application/xslt+xml' => "xml",
		'application/x-x509-ca-cert' => 'text',
		'message/rfc822' => 'text',
		'text/css' => 'css',
		'text/html' => 'xml',
		'text/plain-ascii' => 'ascii',
		'text/plain' => 'text',
		'text/troff' => 'groff',
		'text/x-asm' => 'nasm',
		'text/x-awk' => 'awk',
		'text/x-c' => 'c',
		'text/x-c++' => 'cpp',
		'text/x-c++hdr' => 'c',
		'text/x-chdr' => 'c',
		'text/x-csrc' => 'c',
		'text/x-c++src' => 'cpp',
		'text/x-diff' => 'diff',
		'text/x-gawk' => 'awk',
		'text/x-haskell' => 'haskell',
		'text/x-java' => 'java',
		'text/x-lisp' => 'cl',
		'text/x-literate-haskell' => 'haskell',
		'text/x-lua' => 'lua',
		'text/x-makefile' => 'make',
		'text/x-ocaml' => 'ocaml',
		'text/x-patch' => 'diff',
		'text/x-perl' => 'perl',
		'text/x-php' => 'php',
		'text/x-python' => 'python',
		'text/x-ruby' => 'ruby',
		'text/x-scheme' => 'scheme',
		'text/x-shellscript' => 'bash',
		'text/x-subviewer' => 'bash',
		'text/x-tcl' => 'tcl',
		'text/x-tex' => 'tex',
		);
		if (array_key_exists($type, $typearray)) return $typearray[$type];

		if (strpos($type, 'text/') === 0) return 'text';

		# default
		return false;
	}

	// Map special filenames to lexers
	private function filename2lexer($name)
	{
		$namearray = array(
			'PKGBUILD' => 'bash',
			'.vimrc' => 'vim'
		);
		if (array_key_exists($name, $namearray)) return $namearray[$name];


		if (strpos($name, ".") !== false) {
			$extension = substr($name, strrpos($name, ".") + 1);

			$extensionarray = array(
				'coffee' => 'coffee-script',
				'mli' => 'ocaml',
				'mll' => 'ocaml',
				'ml' => 'ocaml',
				'mly' => 'ocaml',
				'tcl' => 'tcl',
				'tex' => 'tex',
			);
			if (array_key_exists($extension, $extensionarray)) return $extensionarray[$extension];
		}

		return false;
	}

	// Handle lexer aliases
	public function resolve_lexer_alias($alias)
	{
		if ($alias === false) return false;
		$aliasarray = array(
			'py' => 'python',
			'sh' => 'bash',
			's' => 'asm',
			'pl' => 'perl'
		);
		if (array_key_exists($alias, $aliasarray)) return $aliasarray[$alias];

		return $alias;
	}

}

# vim: set noet:
