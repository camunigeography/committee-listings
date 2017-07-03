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
			
			CREATE TABLE `types` (
			  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Automatic key',
			  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Type',
			  `ordering` int(1) NOT NULL DEFAULT '5' COMMENT 'Ordering (1 = first)'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Committee types (for grouping)';
			
			CREATE TABLE `settings` (
			  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Automatic key (ignored)',
			  `homepageIntroductionHtml` text COLLATE utf8_unicode_ci COMMENT 'Homepage introductory content',
			  `homepageFooterHtml` text COLLATE utf8_unicode_ci COMMENT 'Homepage footer content'
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
		
	}
	
	
	# Additional initialisation
	public function main ()
	{
		# Get the Committees
		$this->committees = $this->getCommittees ();
		
	}
	
	
	# Function to get the Committees
	private function getCommittees ()
	{
		# Get the data
		$query = '
			SELECT
				name, moniker, type, spaceAfter, introductionHtml, membersHtml, meetingsHtml
			FROM committees
			LEFT JOIN types ON committees.typeId = types.id
			ORDER BY types.ordering, committees.ordering, committees.name
		;';
		$data = $this->databaseConnection->getData ($query);
		
		# Reindex by moniker
		$data = application::reindex ($data, 'moniker');
		
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
				$isExternal = (preg_match ('|^https?://.+|', $committee['moniker']));
				$url = ($isExternal ? $moniker : $this->baseUrl . '/' . $moniker . '/');
				$list[] = "<a href=\"{$url}\"" . ($isExternal ? ' target="_blank"' : '') . ($committee['spaceAfter'] ? ' class="spaced"' : '') . '>' . htmlspecialchars ($committee['name']) . '</a>';
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
		$meetings = $this->getMeetings ($committeeId);
		
		# Construct the HTML
		$html = '';
		$html .= "\n<h2>" . htmlspecialchars ($committee['name']) . '</h2>';
		$html .= $committee['introductionHtml'];
		$html .= "\n<h2>Members of the Committee</h2>";
		$html .= $committee['membersHtml'];
		$html .= "\n<h2>Meetings</h2>";
		$html .= $committee['meetingsHtml'];
		$html .= $this->meetingsTable ($meetings);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to obtain meetings data
	private function getMeetings ($committeeId)
	{
		#!# For now, return a faked-up datastructure
		return array (
			array (
				'date'		=> '2017-09-13',
				'time'		=> '11:00:00',
				'location'	=> 'Seminar Room',
				'agenda'	=> 'Agenda link',
				'minutes'	=> 'Minutes link',
			),
			array (
				'date'		=> '2017-08-10',
				'time'		=> '12:00:00',
				'location'	=> 'Seminar Room',
				'agenda'	=> 'Agenda link',
				'minutes'	=> 'Minutes link',
			),
		);
	}
	
	
	# Function to convert a meetings list to a table
	private function meetingsTable ($meetings)
	{
		# Start the HTML
		$html = '1';
		
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
				'agenda' => $meeting['agenda'],
				'minutes' => $meeting['minutes'],
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
			'richtextEditorToolbarSet' => 'BasicLonger',
			'richtextWidth' => 600,
			'richtextHeight' => 200,
		);
		
		# Define table attributes
		$attributes = array (
			// array (database, table, field, modifiers array() ),
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
			),
		);
		
		# Run the main settings system with the overriden attributes
		return parent::settings ($dataBindingSettingsOverrides);
	}
}

?>