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
			'editing' => array (
				'description' => false,
				'url' => 'data/',
				'tab' => 'Data editing',
				'icon' => 'pencil',
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
			  `time` time NOT NULL COMMENT 'Time',
			  `location` VARCHAR(255) NOT NULL COMMENT 'Location'
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
			$url = $committee['url'];
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
			$data[$moniker]['url'] = ($data[$moniker]['isExternal'] ? $moniker : $this->baseUrl . '/' . $moniker . '/');
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
				$list[] = "<a href=\"{$committee['url']}\"" . ($committee['isExternal'] ? ' target="_blank"' : '') . ($committee['spaceAfter'] ? ' class="spaced"' : '') . '>' . htmlspecialchars ($committee['name']) . '</a>';
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
			$html .= "\n<h2>" . "<a href=\"{$committee['url']}\"" . ($committee['isExternal'] ? ' target="_blank"' : '') . '>' . htmlspecialchars ($committee['name']) . '</a></h2>';
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
		
		# Obtain the meetings for this committee
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
		
		# Get the files for this committee
		$files = $this->getFiles ($committee['url']);
		
		# Attach document metadata
		$groupings = array ('agenda', 'minutes', 'papers');
		foreach ($meetings as $id => $meeting) {
			$folder = preg_replace ('/^([0-9]{2})([0-9]{2})-([0-9]{2})-([0-9]{2})/', '\2\3\4', $meeting['date']);	// E.g. 2017-07-11 has folder 170711
			foreach ($groupings as $grouping) {
				$meetings[$id][$grouping]  = (isSet ($files[$folder]) && isSet ($files[$folder][$grouping])  ? $files[$folder][$grouping]  : array ());
			}
		}
		
		# Return the data
		return $meetings;
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
		foreach ($meetings as $meeting) {
			$table[] = array (
				'date' => nl2br (date ("l jS F Y\nga", strtotime ($meeting['date'] . ' ' . $meeting['time']))) . ', ' . htmlspecialchars ($meeting['location']),
				'agenda'  => ($meeting['agenda']  ? "<a href=\"{$committee['url']}{$meeting['agenda']}\">Agenda</a>"   : ''),
				'minutes' => ($meeting['minutes'] ? "<a href=\"{$committee['url']}{$meeting['minutes']}\">Minutes</a>" : ''),
			);
		}
		
		# Define labels
		$headings = array (
			'date' => 'Date',
			'agenda' => 'Agendas and<br />other papers',
			'minutes' => 'Minutes (more recent meetings<br />may introduce corrections)',
		);
		
		# Render the table
		$html = application::htmlTable ($table, $headings, 'graybox', $keyAsFirstColumn = false, false, $allowHtml = true);
		
		# Return the HTML
		return $html;
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
