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
			'supportedFileTypes' => array ('doc', 'docx', 'pdf', 'xls', 'xlsx', ),
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
			'documents' => array (
				'description' => false,
				'url' => '%1/%2/documents.html',
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
			  `typeId` INT(11) NOT NULL COMMENT 'Type',
			  `ordering` ENUM('1','2','3','4','5','6','7','8','9') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '5' COMMENT 'Ordering (1 = first)',
			  `spaceAfter` INT(1) NULL COMMENT 'Add space after?',
			  `introductionHtml` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT 'Introduction text',
			  `membersHtml` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL COMMENT 'Members',
			  `meetingsHtml` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL COMMENT 'Meetings (clarification text)',
			  UNIQUE(`moniker`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Committees';
			
			CREATE TABLE `meetings` (
			  `id` int(11) NOT NULL COMMENT 'Automatic key',
			  `committeeId` int(11) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Committee',
			  `date` date NOT NULL COMMENT 'Date',
			  `time` time COMMENT 'Time',
			  `location` VARCHAR(255) COMMENT 'Location',
			  `note` VARCHAR(255) NULL COMMENT 'Note',
			  `rescheduledFrom` DATE NULL COMMENT 'Rescheduled from date',
			  `isCancelled` INT(1) NULL COMMENT 'Meeting cancelled?'
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
				committees.id, name, moniker, type, spaceAfter, introductionHtml, membersHtml, meetingsHtml
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
	public function show ($committeeId)
	{
		# Ensure the committee exists
		if (!$committeeId || !isSet ($this->committees[$committeeId])) {
			echo $this->page404 ();
			return false;
		}
		$committee = $this->committees[$committeeId];
		
		# Obtain the meetings and associated papers for this committee
		$meetings = $this->getMeetings ($committee);
		
		# Construct the HTML
		$html  = '';
		$html .= "\n<h2>" . htmlspecialchars ($committee['name']) . '</h2>';
		if ($this->userIsAdministrator) {
			$html .= "<p class=\"actions right\" id=\"editlink\"><a href=\"{$this->baseUrl}/data/committees/{$committee['id']}/edit.html\"><img src=\"/images/icons/pencil.png\" class=\"icon\" /> Edit</a></p>";
		}
		$html .= $committee['introductionHtml'];
		$html .= "\n<h2>Members of the Committee</h2>";
		$html .= $committee['membersHtml'];
		$html .= "\n<h2>Meetings</h2>";
		$html .= $committee['meetingsHtml'];
		if ($this->userIsAdministrator) {
			$html .= "<p class=\"actions right\">
				<a href=\"{$this->baseUrl}/data/meetings/add.html?committee={$committee['id']}\"><img src=\"/images/icons/pencil.png\" class=\"icon\" /> Add</a>
				<a href=\"{$this->baseUrl}/data/meetings/\"><img src=\"/images/icons/pencil.png\" class=\"icon\" /> Edit</a>
			</p>";
		}
		$html .= $this->meetingsTable ($meetings, $committee);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to obtain meetings data
	private function getMeetings ($committee)
	{
		# Get the data
		$meetings = $this->databaseConnection->select ($this->settings['database'], 'meetings', array ('committeeId' => $committee['id']), array (), true, 'date DESC, time DESC');
		
		# Attach six-figure date format; e.g. 2017-04-24 would be 170424
		foreach ($meetings as $id => $meeting) {
			$meetings[$id]['date6'] = $this->sqlDateToDate6 ($meeting['date']);
		}
		
		# Get the files for this committee
		$files = $this->getFiles ($committee['path'] . '/');
		
		# Attach document metadata
		$groupings = array ('agenda', 'minutes', 'papers');
		foreach ($meetings as $id => $meeting) {
			$folder = $meeting['date6'];
			foreach ($groupings as $grouping) {
				$meetings[$id][$grouping]  = (isSet ($files[$folder]) && isSet ($files[$folder][$grouping])  ? $files[$folder][$grouping]  : array ());
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
		
		# Organise files by date, flagging undated files (which should either be in a dated folder or have a date in the filename)
		$files = array ();
		foreach ($filesRaw as $index => $path) {
			if (!preg_match ('/([0-9]{6})/', $path, $matches)) {
				echo "<p class=\"warning\">Error: path <tt>{$path}</tt> is undated.</p>";
			}
			$date = $matches[1];
			$files[$date]['papers'][] = $path;
		}
		
		# Sort groups by date
		ksort ($files);
		
		# Extract agenda and minutes
		foreach ($files as $date => $papers) {
			foreach ($papers['papers'] as $index => $path) {
				
				# Main documents (agendas and minutes) should always be in the top level, not in a subfolder
				if (substr_count ($path, '/') != 1) {continue;}
				
				# Extract files
				$groupings = array ('agenda', 'minutes', 'notes');
				foreach ($groupings as $grouping) {
					
					# Group type name is just before a dot, e.g. Staff170711Agenda.doc
					#!# Need to detect multiple matches, e.g. .doc and .pdf
					if (substr_count ($path, ucfirst ($grouping) . '.')) {
						$files[$date][$grouping] = $path;
						unset ($files[$date]['papers'][$index]);
					}
				}
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
		foreach ($meetings as $id => $meeting) {
			
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
					$agenda .= $this->additionalPapersListing ($meeting['papers'], $committee['path']);
				}
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
			$table[$id] = array (
				'date'		=> $date,
				'agenda'	=> $agenda,
				'minutes'	=> $minutes,
			);
			if ($this->userIsAdministrator) {
				$table[$id]['edit']  = "<a title=\"Add/remove documents\" href=\"{$committee['path']}/{$meeting['date6']}/documents.html\" class=\"document\"><img src=\"/images/icons/page_white_add.png\" class=\"icon\" /></a>";
				$table[$id]['edit'] .= "<a title=\"Edit meeting details\" href=\"{$this->baseUrl}/data/meetings/{$id}/edit.html\"><img src=\"/images/icons/pencil.png\" class=\"icon\" /></a>";
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
	
	
	# Function to provide document management
	public function documents ($committeeId)
	{
		# Ensure the committee exists
		if (!$committeeId || !isSet ($this->committees[$committeeId])) {
			echo $this->page404 ();
			return false;
		}
		$committee = $this->committees[$committeeId];
		
		
		application::dumpData ($committee);
		application::dumpData ($_GET);
		
		
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
