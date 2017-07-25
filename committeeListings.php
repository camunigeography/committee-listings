<?php

# Class to create a committee listings system


require_once ('frontControllerApplication.php');
class committeeListings extends frontControllerApplication
{
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'applicationName' => 'Committees',
			'div' => strtolower (__CLASS__),
			'database' => 'committeelistings',
			'table' => 'meetings',
			'databaseStrictWhere' => true,
			'administrators' => true,
			'useEditing' => true,
			'supportedFileTypes' => array ('pdf', 'doc', 'docx', 'xls', 'xlsx', ),
			'uploadTypesText' => 'Agendas/minutes should ideally be in PDF format, but Word documents are also acceptable. (Excel is also permitted for additional papers.)',
			'paperUploadSlots' => 5,
			'usersAutocomplete' => false,
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Function assign additional actions
	public function actions ()
	{
		# Specify additional actions
		$actions = array (
			'home' => array (
				'description' => false,
				'url' => '',
				'tab' => 'Committees',
				'icon' => 'house',
			),
			'membership' => array (
				'description' => 'Committee membership',
				'url' => 'membership/',
				'tab' => 'Members',
			),
			'show' => array (
				'description' => false,
				'url' => '%1/',
				'usetab' => 'home',
			),
			'meeting' => array (
				'description' => false,		// Custom description set on the page
				'url' => '%1/%2/add.html',
				'usetab' => 'home',
			),
			'editing' => array (
				'description' => false,
				'url' => 'data/',
				'tab' => 'Data editing',
				'icon' => 'pencil',
				'administrator' => true,
			),
			'import' => array (
				'description' => 'Import',
				'url' => 'import/',
				'subtab' => 'Import',
				'parent' => 'admin',
				'administrator' => true,
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Database structure definition
	public function databaseStructure ()
	{
		return "
			CREATE TABLE `administrators` (
			  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL PRIMARY KEY COMMENT 'Username',
			  `active` enum('','Yes','No') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Yes' COMMENT 'Currently active?'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='System administrators';
			
			CREATE TABLE `committees` (
			  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Automatic key',
			  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Name',
			  `moniker` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'URL moniker',
			  `prefixFilename` VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci COMMENT 'Document prefix'
			  `typeId` INT(11) NOT NULL COMMENT 'Type',
			  `managers` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Managers (usernames, one per line)',
			  `ordering` ENUM('1','2','3','4','5','6','7','8','9') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '5' COMMENT 'Ordering (1 = first)',
			  `spaceAfter` INT(1) NULL COMMENT 'Add space after?',
			  `introductionHtml` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT 'Introduction text',
			  `membersHtml` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL COMMENT 'Members',
			  `meetingsHtml` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL COMMENT 'Meetings (clarification text)',
			  UNIQUE(`moniker`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Committees';
			
			CREATE TABLE `meetings` (
			  `id` int(11) NOT NULL PRIMARY KEY COMMENT 'Automatic key',
			  `committeeId` int(11) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Committee',
			  `date` date NOT NULL COMMENT 'Date',
			  `time` time COMMENT 'Time',
			  `location` VARCHAR(255) COMMENT 'Location',
			  `note` VARCHAR(255) NULL COMMENT 'Note',
			  `rescheduledFrom` DATE NULL COMMENT 'Rescheduled from date',
			  `isCancelled` INT(1) NULL COMMENT 'Meeting cancelled?',
			  UNIQUE KEY `committeeId_date`(`committeeId`, `date`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Meetings';
			
			CREATE TABLE `types` (
			  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Automatic key',
			  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Type',
			  `ordering` int(1) NOT NULL DEFAULT '5' COMMENT 'Ordering (1 = first)'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Committee types (for grouping)';
			
			CREATE TABLE `settings` (
			  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Automatic key (ignored)',
			  `homepageIntroductionHtml` text COLLATE utf8_unicode_ci COMMENT 'Homepage introductory content',
			  `homepageFooterHtml` text COLLATE utf8_unicode_ci COMMENT 'Homepage footer content',
			  `membershipIntroductionHtml` TEXT NULL COMMENT 'Membership page introduction'
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Settings';
		";
	}
	
	
	# Additional initialisation, pre-actions
	public function mainPreActions ()
	{
		# Hide tabs to ordinary users
		if (!$this->userIsAdministrator) {
			$this->settings['disableTabs'] = true;
		}
		
		# Get the Committees
		$this->committees = $this->getCommittees ();
		
		# Determine selected committee, if any, rejecting invalid values
		$this->committee = false;
		if (isSet ($_GET['committee']) && strlen ($_GET['committee'])) {
			if (!isSet ($this->committees[$_GET['committee']])) {
				$html = $this->page404 ();
				echo $html;
				return false;
			}
			$this->committee = $_GET['committee'];
		}
		
		# Customise page title to committee if specified
		if ($this->committee) {
			$this->settings['applicationName'] = $this->committees[$this->committee]['name'];
		}
		
	}
	
	
	# Additional initialisation
	public function main ()
	{
	}
	
	
	# Drop-down list for switching committee
	public function guiSearchBox ()
	{
		# End if not enabled
		$enableActions = array ('home', 'show');
		if (!in_array ($this->action, $enableActions)) {return false;}
		
		# Create the droplist
		$droplist = array ();
		$droplist[$this->baseUrl . '/'] = 'List of committees';
		$truncateTo = 30;
		foreach ($this->committees as $committee) {
			$url = $committee['path'] . '/';
			$droplist[$url] = (mb_strlen ($committee['name']) > $truncateTo ? mb_substr ($committee['name'], 0, $truncateTo) . chr(0xe2).chr(0x80).chr(0xa6) : $committee['name']);
		}
		
		# Compile the HTML
		$html = pureContent::htmlJumplist ($droplist, $selected = $_SERVER['SCRIPT_NAME'], '', 'jumplist', 0, $class = 'jumplist ultimateform');
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get the Committees
	private function getCommittees ()
	{
		# Get the data
		$query = '
			SELECT
				committees.id, name, moniker, prefixFilename, type, managers, spaceAfter, introductionHtml, membersHtml, meetingsHtml
			FROM committees
			LEFT JOIN types ON committees.typeId = types.id
			ORDER BY types.ordering, committees.ordering, committees.name
		;';
		$data = $this->databaseConnection->getData ($query);
		
		# Reindex by moniker
		$data = application::reindex ($data, 'moniker');
		
		# Add link data to the model
		foreach ($data as $moniker => $committee) {
			$data[$moniker]['isExternal'] = (preg_match ('|^https?://.+|', $moniker));
			$data[$moniker]['path'] = ($data[$moniker]['isExternal'] ? $moniker : $this->baseUrl . '/' . $moniker);
		}
		
		# Convert managers list, and add whether the user has editing rights
		foreach ($data as $moniker => $committee) {
			$data[$moniker]['managers'] = (trim ($committee['managers']) ? explode (', ', $committee['managers']) : array ());
			$data[$moniker]['editRights'] = ($this->user && ($this->userIsAdministrator || in_array ($this->user, $data[$moniker]['managers'])));
		}
		
		# Return the data
		return $data;
	}
	
	
	
	# Welcome screen
	public function home ()
	{
		# Regroup by type
		$committeesByType = application::regroup ($this->committees, 'type');
		
		# Create the listing
		$listingHtml = '';
		$multipleTypes = (count ($committeesByType) > 1);
		foreach ($committeesByType as $type => $committees) {
			
			# Show heading if more than one type
			if ($multipleTypes) {
				$listingHtml .= "\n<h2>" . htmlspecialchars ($type) . '</h2>';
			}
			
			# Create the list for this type
			$list = array ();
			foreach ($committees as $moniker => $committee) {
				$list[] = "<a href=\"{$committee['path']}/\"" . ($committee['isExternal'] ? ' target="_blank"' : '') . ($committee['spaceAfter'] ? ' class="spaced"' : '') . '>' . htmlspecialchars ($committee['name']) . '</a>';
			}
			$listingHtml .= application::htmlUl ($list);
		}
		
		# Compile the HTML
		$html  = $this->settings['homepageIntroductionHtml'];
		$html .= $listingHtml;
		$html .= $this->settings['homepageFooterHtml'];
		
		# Show the HTML
		echo $html;
	}
	
	
	# Committee membership listing
	public function membership ()
	{
		# Start the HTML
		$html = '';
		
		# Construct the HTML, looping through each Committee and list the members
		$html .= $this->settings['membershipIntroductionHtml'];
		foreach ($this->committees as $moniker => $committee) {
			$html .= "\n<h2>" . "<a href=\"{$committee['path']}/\"" . ($committee['isExternal'] ? ' target="_blank"' : '') . '>' . htmlspecialchars ($committee['name']) . '</a></h2>';
			$html .= $committee['membersHtml'];
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Committee page
	public function show ()
	{
		# Ensure the committee is specified
		if (!$this->committee) {
			echo $this->page404 ();
			return false;
		}
		$committee = $this->committees[$this->committee];
		
		# Obtain the meetings and associated papers for this committee
		$meetings = $this->getMeetings ($committee);
		
		# Construct the HTML
		$html  = '';
		$html .= "\n<h2>" . htmlspecialchars ($committee['name']) . '</h2>';
		if ($committee['editRights']) {
			$html .= "<p class=\"actions right\" id=\"editlink\"><a href=\"{$this->baseUrl}/data/committees/{$committee['id']}/edit.html\"><img src=\"/images/icons/pencil.png\" class=\"icon\" /> Edit</a></p>";
		}
		$html .= $committee['introductionHtml'];
		$html .= "\n<h2>Members of the Committee</h2>";
		$html .= $committee['membersHtml'];
		$html .= "\n<h2>Meetings</h2>";
		$html .= $committee['meetingsHtml'];
		if ($committee['editRights']) {
			$html .= "<p class=\"actions right\">
				<a href=\"{$this->baseUrl}/data/meetings/add.html?committee={$committee['id']}\"><img src=\"/images/icons/pencil.png\" class=\"icon\" /> Add</a>
				<a href=\"{$this->baseUrl}/data/meetings/\"><img src=\"/images/icons/pencil.png\" class=\"icon\" /> Edit</a>
			</p>";
		}
		$html .= $this->meetingsTable ($meetings, $committee);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to obtain meetings data, indexed by six-figure date; there is an assumption of only one meeting per committee per day
	private function getMeetings ($committee)
	{
		# Get the data
		$meetingsById = $this->databaseConnection->select ($this->settings['database'], 'meetings', array ('committeeId' => $committee['id']), array (), true, 'date DESC, time DESC');
		
		# Reindex by six-figure date; e.g. 2017-04-24 would be 170424
		$meetings = array ();
		foreach ($meetingsById as $id => $meeting) {
			$date6 = $this->sqlDateToDate6 ($meeting['date']);
			$meetings[$date6] = $meeting;
		}
		
		# Get the files for this committee
		$files = $this->getFiles ($committee['path'] . '/');
		
		# Attach document metadata
		$groupings = array ('documents', 'agenda', 'minutes', 'papers');
		foreach ($meetings as $date6 => $meeting) {
			foreach ($groupings as $grouping) {
				$meetings[$date6][$grouping] = (isSet ($files[$date6]) && isSet ($files[$date6][$grouping]) ? $files[$date6][$grouping] : array ());
			}
		}
		
		# Return the data
		return $meetings;
	}
	
	
	# Function to convert SQL date format to date6; e.g. 2017-04-24 would be 170424
	private function sqlDateToDate6 ($sqlDate)
	{
		return $date6 = preg_replace ('/^([0-9]{2})([0-9]{2})-([0-9]{2})-([0-9]{2})/', '\2\3\4', $sqlDate);
	}
	
	
	# Function to parse the filesystem for files for each meeting
	private function getFiles ($folder)
	{
		# Get files in the directory
		$directory = $_SERVER['DOCUMENT_ROOT'] . $folder;
		require_once ('directories.php');
		$filesRaw = directories::flattenedFileListing ($directory, $this->settings['supportedFileTypes'], $includeRoot = false);
		
		# Organise files by date, skipping additional undated folders (as files should either be in a dated folder or have a date in the filename)
		$files = array ();
		foreach ($filesRaw as $index => $path) {
			if (!preg_match ('/([0-9]{6})/', $path, $matches)) {
				// echo "<p class=\"warning\">Warning: path <tt>{$path}</tt> is undated.</p>";
				continue;
			}
			$date6 = $matches[1];
			$files[$date6]['documents'][] = $path;	// All documents
		}
		
		# Sort groups by date
		ksort ($files);
		
		# Classify each document into agenda, minutes, or papers
		foreach ($files as $date6 => $papers) {
			foreach ($papers['documents'] as $index => $path) {
				
				# Extract main documents (agendas and minutes), which should always be in the top level, not in a subfolder
				if (substr_count ($path, '/') == 1) {
					$groupings = array ('agenda', 'minutes', 'notes');
					foreach ($groupings as $grouping) {
						
						# Group type name is just before a dot, e.g. Staff170711Agenda.doc
						#!# Need to detect multiple matches, e.g. .doc and .pdf
						if (substr_count ($path, ucfirst ($grouping) . '.')) {
							$files[$date6][$grouping] = $path;
							continue 2;		// Found, so continue to next file
						}
					}
				}
				
				# Register as general paper
				$files[$date6]['papers'][] = $path;
			}
		}
		
		// application::dumpData ($files);
		
		# Get the list of files
		return $files;
	}
	
	
	# Function to convert a meetings list to a table
	private function meetingsTable ($meetings, $committee)
	{
		# End if none
		if (!$meetings) {
			$html = "\n<p><em>No meetings have been found for this Committee.</em></p>";
			return $html;
		}
		
		# Compile the table data
		$table = array ();
		foreach ($meetings as $date6 => $meeting) {
			
			# Date
			$dateIsFuture = ($meeting['date'] > date ('Y-m-d'));
			$dateFormat = ($dateIsFuture ? 'l ' : '') . 'j<\s\u\p>S</\s\u\p> F Y';
			$date  = '';
			$date .= date ($dateFormat, strtotime ($meeting['date']));
			if ($meeting['time'] || $meeting['location']) {
				$date .= '<br />';
			}
			if ($meeting['time']) {
				$date .= date ('ga', strtotime ($meeting['date'] . ' ' . $meeting['time']));
			}
			if ($meeting['location']) {
				$date .= ', ' . htmlspecialchars ($meeting['location']);
			}
			if ($meeting['isCancelled']) {
				$date = '<s>' . $date . '</s>';
			}
			
			# Agenda
			$agenda = '';
			if ($meeting['isCancelled']) {
				$agenda .= 'Meeting cancelled';
			} else {
				if ($meeting['agenda']) {
					$agenda .= "<a href=\"{$committee['path']}{$meeting['agenda']}\">Agenda</a>";
				}
				if ($meeting['papers']) {
					if (!$meeting['agenda']) {
						$agenda .= 'Agenda (not online at present)';
					}
				}
			}
			
			# Papers, which should be shown even if there is no agenda or if the meeting is cancelled
			if ($meeting['papers']) {
				$agenda .= $this->additionalPapersListing ($meeting['papers'], $committee['path']);
			}
			
			# Minutes
			$minutes = '';
			if ($meeting['isCancelled']) {
				$minutes .= '';
			} else {
				if ($meeting['minutes']) {
					$minutes .= "<a href=\"{$committee['path']}{$meeting['minutes']}\">Minutes</a>";
				}
			}
			
			# Register the entry
			$table[$date6] = array (
				'date'		=> "<a id=\"meeting{$date6}\"></a>" . $date,
				'agenda'	=> $agenda,
				'minutes'	=> $minutes,
			);
			if ($committee['editRights']) {
				$table[$date6]['edit']  = "<a title=\"Edit meeting details\" href=\"{$committee['path']}/{$date6}/\"><img src=\"/images/icons/pencil.png\" class=\"icon\" /></a>";
				$table[$date6]['edit'] .= "<a title=\"Add/remove documents\" href=\"{$committee['path']}/{$date6}/add.html\" class=\"document\"><img src=\"/images/icons/page_copy.png\" class=\"icon\" /></a>";
			}
		}
		
		# Define labels
		$headings = array (
			'date'		=> 'Date',
			'agenda'	=> 'Agendas and<br />other papers',
			'minutes'	=> 'Minutes (more recent meetings<br />may introduce corrections)',
			'edit'		=> 'Edit',
		);
		
		# Render the table
		$html = application::htmlTable ($table, $headings, 'meetings graybox', $keyAsFirstColumn = false, false, $allowHtml = true, false, $addCellClasses = true);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to list additional papers
	private function additionalPapersListing ($papers, $committeePath)
	{
		# End if none
		if (!$papers) {return false;}
		
		# Convert to a list of links
		$truncateTo = 60;
		$list = array ();
		foreach ($papers as $path) {
			$title = htmlspecialchars (pathinfo ($path, PATHINFO_FILENAME));
			if (mb_strlen ($title) > $truncateTo) {
				$title = mb_substr ($title, 0, $truncateTo) . '&hellip;';
			}
			$list[] = "<a href=\"{$committeePath}" . htmlspecialchars (implode ('/', array_map ('rawurlencode', explode ('/', $path)))) . '">' . $title . '</a>';
		}
		
		# Convert to HTML
		$html = "\n" . application::htmlUl ($list, 3, 'additionalpapers');
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to provide management of details for a specific meeting
	public function meeting ()
	{
		# Start the HTML
		$html = '';
		
		# Ensure the committee is specified
		if (!$this->committee) {
			echo $this->page404 ();
			return false;
		}
		$committee = $this->committees[$this->committee];
		
		# Ensure the user has rights
		if (!$committee['editRights']) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Ensure there is a date6 parameter supplied
		if (!isSet ($_GET['date6']) || !preg_match ('/^([0-9]{6})$/', $_GET['date6'])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		$date6 = $_GET['date6'];
		
		# Obtain the meetings and associated papers for this committee
		$meetings = $this->getMeetings ($committee);
		
		# Validate the existence of the meeting
		if (!isSet ($meetings[$date6])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		$meeting = $meetings[$date6];
		
		# Define available pages, and links for use in tabs
		$pages = array (
			'edit'		=> "<a href=\"{$committee['path']}/{$date6}/\"><img src=\"/images/icons/page_add.png\" class=\"icon\" /> Meeting details</a>",
			'add'		=> "<a href=\"{$committee['path']}/{$date6}/add.html\"><img src=\"/images/icons/page_add.png\" class=\"icon\" /> Add document(s)</a>",
			'delete'	=> "<a" . (!$meeting['documents'] ? ' class="empty"' : '') . " href=\"{$committee['path']}/{$date6}/delete.html\"><img src=\"/images/icons/page_delete.png\" class=\"icon\" /> Delete document(s)</a>",
		);
		
		# Validate action
		if (!$_GET['page'] || !isSet ($pages[$_GET['page']])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		$page = $_GET['page'];
		
		# Page description
		$html .= "\n<h2><a href=\"{$committee['path']}/\">" . htmlspecialchars ($committee['name']) . '</a> &raquo; ' . date ('l j<\s\u\p>S</\s\u\p> F Y', strtotime ($meeting['date'])) . '</h2>';
		
		# Add tabs
		$html .= application::htmlUl ($pages, 0, 'tabs', true, false, false, false, $page);
		
		# Run the page
		$method = 'meeting' . ucfirst ($page);
		$html .= $this->{$method} ($committee, $meeting, $date6);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to provide a meeting editing form
	private function meetingEdit ($committee, $meeting, $date6)
	{
		# Start the HTML
		$html  = "\n<h3>Meeting details</h3>";
		
		# Create the editing form
		$form = new form (array (
			'div' => 'ultimateform lines horizontalonly',
			'formCompleteText' => false,
			'displayRestrictions' => false,
			'databaseConnection' => $this->databaseConnection,
			'unsavedDataProtection' => true,
			'div' => 'graybox ultimateform lines horizontalonly',
			'picker' => true,
		));
		$form->dataBinding (array (
			'database' => $this->settings['database'],
			'table' => $this->settings['table'],
			'exclude' => array ('id', 'committeeId', ),
			'int1ToCheckbox' => true,
			'data' => $meeting,
			'attributes' => array (
				'location' => array ('description' => 'Please avoid abbreviations.'),
				'note' => array ('description' => 'This note will be public.'),
			),
		));
		if ($result = $form->process ($html)) {
			
			# Update the data
			$this->databaseConnection->update ($this->settings['database'], $this->settings['table'], $result, array ('id' => $meeting['id']));
			
			# Determine the new date6
			$newdate6 = $this->sqlDateToDate6 ($result['date']);
			
			# Confirmation message, resetting the HTML
			$html  = "\n<p>{$this->tick} Meeting details successfully updated.</p>";
			$html .= "\n<p><a href=\"{$committee['path']}/#meeting{$newdate6}\">Return to the committee page</a>, where it is shown.</p>";
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to provide a document upload form
	private function meetingAdd ($committee, $meeting, $date6)
	{
		# Start the HTML
		$html = "\n<h3>Add document(s)</h3>";
		
		# Determine filenames
		$filenames = array (
			'agenda' => $committee['prefixFilename'] . $date6 . 'Agenda',
			'minutes' => $committee['prefixFilename'] . $date6 . 'Minutes',
		);
		
		# Create the upload form
		$form = new form (array (
			'div' => 'ultimateform lines horizontalonly',
			'formCompleteText' => false,
			'displayRestrictions' => false,
			'unsavedDataProtection' => true,
		));
		$form->heading ('p', $this->settings['uploadTypesText']);
		$form->heading (4, 'Agenda:');
		if ($meeting['agenda']) {
			$form->heading ('', "<p>There is currently an <a href=\"{$committee['path']}{$meeting['agenda']}\" target=\"_blank\" title=\"[Link opens in a new window]\">existing agenda file</a>. Please delete it below first if you wish to add a new version.</p>");
		} else {
			$form->upload (array (
				'name'				=> 'agenda',
				'title'				=> 'Agenda',
				'allowedExtensions'	=> array ('pdf', 'doc', 'docx'),
				'directory'			=> $_SERVER['DOCUMENT_ROOT'] . $committee['path'] . '/',
				'forcedFileName'	=> $filenames['agenda'],
			));
		}
		$form->heading (4, 'Minutes:');
		if ($meeting['minutes']) {
			$form->heading ('', "<p>There is currently an <a href=\"{$committee['path']}{$meeting['minutes']}\" target=\"_blank\" title=\"[Link opens in a new window]\">existing minutes file</a>. Please delete it below first if you wish to add a new version.</p>");
		} else {
			$form->upload (array (
				'name'				=> 'minutes',
				'title'				=> 'Minutes',
				'allowedExtensions'	=> array ('pdf', 'doc', 'docx'),
				'directory'			=> $_SERVER['DOCUMENT_ROOT'] . $committee['path'] . '/',
				'forcedFileName'	=> $filenames['minutes'],
			));
		}
		$form->heading (4, 'Add additional papers under agenda:');
		for ($i = 1; $i <= $this->settings['paperUploadSlots']; $i++) {
			$form->upload (array (
				'name'				=> 'papers' . $i,
				'title'				=> "Paper ({$i})",
				'allowedExtensions'	=> $this->settings['supportedFileTypes'],
				'directory'			=> $_SERVER['DOCUMENT_ROOT'] . $committee['path'] . '/' . $date6 . '/',
			));
		}
		
		# Process the form
		if ($result = $form->process ($html)) {
			
			# Confirmation message, resetting the HTML
			$html  = "\n<p>{$this->tick} File(s) successfully added.</p>";
			$html .= "\n<p><a href=\"{$committee['path']}/\">Return to the committee page</a>, where it is now shown.</p>";
		}
		
		# Surround with a box
		$html = "\n<div class=\"graybox\">" . $html . "\n</div>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to provide a document deletion form
	private function meetingDelete ($committee, $meeting, $date6)
	{
		# Start the HTML
		$html = "\n<h3>Delete document</h3>";
		
		# End if no files
		if (!$meeting['documents']) {
			$html = "\n<p>There are currently no documents for this meeting.</p>";
			return $html;
		}
		
		# Compile the files list
		$files = array ();
		if ($meeting['agenda']) {
			$files[$meeting['agenda']] = 'Agenda';
		}
		if ($meeting['papers']) {
			foreach ($meeting['papers'] as $paper) {
				$files[$paper] = 'Paper: ' . pathinfo ($paper, PATHINFO_FILENAME);
			}
		}
		if ($meeting['minutes']) {
			$files[$meeting['minutes']] = 'Minutes';
		}
		
		# Create the deletion form
		$form = new form (array (
			'div' => 'ultimateform lines horizontalonly',
			'formCompleteText' => false,
			'displayRestrictions' => false,
			'nullText' => false,
			'unsavedDataProtection' => true,
		));
		$form->select (array (
			'name'		=> 'file',
			'title'		=> 'File to delete',
			'values'	=> $files,
			'required'	=> true,
		));
		$form->input (array (
			'name'		=> 'confirm',
			'title'		=> 'Confirm, by typing in YES',
			'required'	=> true,
			'regexp'	=> '^YES$',
			'discard'	=> true,
		));
		
		# Process the form
		if ($result = $form->process ($html)) {
			
			# Delete file
			$file = $_SERVER['DOCUMENT_ROOT'] . $committee['path'] . $result['file'];
			if (!unlink ($file)) {
				$html = "\n<p class=\"warning\">There was a problem deleting the file.</p>";
				#!# Inform admin
			}
			
			# Confirmation message, resetting the HTML
			$html = "\n<p>{$this->tick} File successfully deleted.</p>";
			$html .= "\n<p><a href=\"{$committee['path']}/\">Return to the committee page.</a></p>";
		}
		
		# Surround with a box
		$html = "\n<div class=\"graybox\">" . $html . "\n</div>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to convert existing tabular HTML files to meeting entries
	public function import ()
	{
		# Ensure errors are shown
		ini_set ('display_errors', 1);
		
		# Disable by default
		$html = "\n<p class=\"warning\"><em>Not enabled - please enable in the code. Note that doing so may truncate all existing meetings data - please check the code.</em></p>";
		echo $html;
		return;
		
		# Loop through each Committee
		$meetings = array ();
		$i = 0;
		foreach ($this->committees as $committee) {
			
			# Skip if external
			if (substr_count ($committee['path'], 'http://')) {continue;}
			
			# Title
			echo "<h3>{$committee['name']}</h3>";
			
			# Load the HTML
			$oldPage = $_SERVER['DOCUMENT_ROOT'] . str_replace ('/committees2', '/committees', $committee['path']) . '/index.html';
			$oldHtml = file_get_contents ($oldPage);
			$dom = new DOMDocument();
			$dom->loadHTML ($oldHtml);
			/*
			// See: https://stackoverflow.com/questions/17612865/extracting-a-specific-row-of-a-table-by-domdocument
			$table = $dom->getElementsByTagName ('table')->item(0);
			foreach ($table->getElementsByTagName('tr') as $tr) {
				$tds = $tr->getElementsByTagName('td');
				// $dateRaw = $tds->item(0)->nodeValue;
				$td = $dom->saveXML($tds->item(0));
			}
			*/
			# Obtain the meeting details for each entry in the table
			# See: https://stackoverflow.com/a/37217886/180733
			$year = NULL;
			$xPath = new DOMXpath ($dom);
			foreach ($xPath->query ('//table/tr/td[1]') as $tdXml) {
				$dateRaw = ($tdXml->C14N ());
				
				# Normalise the HTML
				$date = str_replace (array ('<td>', '</td>', '<sup>', '</sup>'), '', $dateRaw);
				$date = str_replace ('th? ', 'th ', $date);
				$date = preg_replace ('/ ([0-9]{1,2})-([0-9]{1,2})(am|pm)/', ' \1\3', $date);
				
				# Break off any note prefix
				$note = '';
				if (substr_count ($date, ' - ')) {
					list ($note, $date) = explode (' - ', $date, 2);
					$note = trim ($note);
					$date = trim ($date);
				}
				
				# Break off any rescheduled date prefix
				$rescheduledFrom = '';
				if (preg_match ('@^<s>([0-9]{2}(?:st|nd|rd|th))</s> (([0-9]{2}(?:st|nd|rd|th)) (.+))$@', $date, $matches)) {	// E.g. '<td><s>20th</s> 26th January 2016</td>' becomes date='2016-01-26', rescheduledFrom='2016-01-20'
					$rescheduledFrom = date ('Y-m-d', strtotime (trim ($matches[1]) . ' ' . trim ($matches[4])));
					$date = trim ($matches[2]);
				}
				
				# Split out date from time/location
				$about = '';
				if (substr_count ($date, '<br></br>')) {
					list ($date, $about) = explode ('<br></br>', $date, 2);
					$date = trim ($date);
					$about = trim ($about);
				} else if (substr_count ($date, ',')) {
					list ($date, $about) = explode (',', $date, 2);
					$date = trim ($date);
					$about = trim ($about);
				}
				
				# Split out time and location
				$time = '';
				$location = '';
				if (strlen ($about)) {
					if (substr_count ($about, ',')) {
						list ($time, $location) = explode (',', $about, 2);
						$time = trim ($time);
						$location = trim ($location);
					} else {
						$time = $about;
					}
				}
				
				# Determine if cancelled
				$isCancelled = NULL;
				$cancellationStrings = array ('<s>', '</s>', ' (cancelled)', ' (Cancelled)');
				foreach ($cancellationStrings as $cancellationString) {
					if (substr_count ($date, $cancellationString)) {
						$isCancelled = '1';
						$date = str_replace ($cancellationStrings, '', $date);
						break;
					}
				}
				
				# Extract the year
				if (preg_match ('/.+ ([0-9]{4})$/', $date, $matches)) {
					$year = $matches[1];
				} else {
					$date .= ' ' . ($year - 1);	// Use the year from the previous iteration
				}
				
				# Parse the date
				$date = date ('Y-m-d', strtotime ($date));
				if ($date == '1970-01-01') {
					echo "<p class=\"warning\">Invalid date:</p>";
				}
				
				# Parse the time
				if (strlen ($time)) {
					$time = date ('H:i:s', strtotime ($date . ' ' . $time));
				}
				
				# Compile the meeting entry
				$meeting = array (
					// '_raw' => $dateRaw,
					'committeeId' => $committee['id'],
					'date' => trim ($date),
					'time' => ($time ? $time : NULL),
					'location' => ($location ? $location : NULL),
					'note' => ($note ? $note : NULL),
					'rescheduledFrom' => ($rescheduledFrom ? $rescheduledFrom : NULL),
					'isCancelled' => $isCancelled,
				);
				
				# Register the entry
				$meetings[] = $meeting;
			}
		}
		
		// application::dumpData ($meetings);
		
		# Insert the data, replacing all existing data
		$this->databaseConnection->truncate ($this->settings['database'], 'meetings', true);
		$this->databaseConnection->insertMany ($this->settings['database'], 'meetings', $meetings, 100);
		application::dumpData ($this->databaseConnection->error ());
	}
	
	
	
	# Admin editing section, substantially delegated to the sinenomine editing component
	public function editing ($attributes = array (), $deny = false, $sinenomineExtraSettings = array ())
	{
		# Define sinenomine settings
		$sinenomineExtraSettings = array (
			'int1ToCheckbox' => true,
			'simpleJoin' => true,
			'datePicker' => true,
			'richtextEditorToolbarSet' => 'BasicLonger',
			'richtextWidth' => 600,
			'richtextHeight' => 200,
		);
		
		# Define table attributes
		$attributes = array (
			array ($this->settings['database'], 'meetings', 'committeeId', array ('get' => 'committee')),
			array ($this->settings['database'], 'committees', 'managers', array ('expandable' => ', ', 'autocomplete' => $this->settings['usersAutocomplete'] , 'autocompleteOptions' => array ('delay' => 0), )),
		);
		
		# Define tables to deny editing for
		$deny[$this->settings['database']] = array (
			'administrators',
			'settings',
		);
		
		# Hand off to the default editor, which will echo the HTML
		parent::editing ($attributes, $deny, $sinenomineExtraSettings);
	}
	
	
	# Settings
	public function settings ($dataBindingSettingsOverrides = array ())
	{
		# Define overrides
		$dataBindingSettingsOverrides = array (
			'attributes' => array (
				'homepageIntroductionHtml'	=> array ('editorToolbarSet' => 'BasicLonger', 'width' => 600, 'height' => 150, ),
				'homepageFooterHtml'		=> array ('editorToolbarSet' => 'BasicLonger', 'width' => 600, 'height' => 150, ),
				'membershipIntroductionHtml'	=> array ('editorToolbarSet' => 'BasicLonger', 'width' => 600, 'height' => 150, ),
			),
		);
		
		# Run the main settings system with the overriden attributes
		return parent::settings ($dataBindingSettingsOverrides);
	}
}

?>