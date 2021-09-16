<?php


# Class to create a Virtual Shackleton online gallery
require_once ('frontControllerApplication.php');
class virtualShackleton extends frontControllerApplication
{
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'div'					=> strtolower (__CLASS__),
			'h1'					=> '<h1>Shackleton items from the Archives</h1>',
			'database'				=> 'virtualshackleton',
			'administrators'		=> true,
			'useEditing'			=> true,
			'tabUlClass'			=> 'tabsflat',
			'table'					=> 'articles',
			'databaseStrictWhere'	=> true,
			'imageGenerationStub'	=> '/images/generator',		// Location of the image generation script
			'imageDirectory'		=> '/images/',				// Location of the images directory from baseUrl
			'imageWidth'			=> 200,
			'mediaTypes'			=> array ('.jpg', '.mov'),
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Function to assign supported actions
	public function actions ()
	{
		# Define available actions
		$actions = array (
			'expeditions' => array (
				'description' => 'Expeditions',
				'url' => 'expeditions/',
				'tab' => 'Expeditions',
				'icon' => 'map',
			),
			'authors' => array (
				'description' => 'Authors',
				'url' => 'authors/',
				'tab' => 'Authors',
				'icon' => 'group',
			),
			'articles' => array (
				'description' => 'All articles',
				'url' => 'articles/',
				'tab' => 'Articles',
				'icon' => 'page_white_stack',
			),
			'items' => array (
				'description' => false,		// 'Listing of articles within a category',
				'url' => '%1/%2.html',
			),
			'article' => array (
				'description' => false,
				'url' => 'article/%1.html',
				'usetab' => 'articles',
			),
			'image' => array (
				'description' => false,		// 'Show image',
				'url' => 'images/%1.jpg',
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Database structure definition
	public function databaseStructure ()
	{
		return "
			-- Administrators
			CREATE TABLE `administrators` (
			  `username` varchar(255) NOT NULL COMMENT 'Username',
			  `active` enum('','Yes','No') NOT NULL DEFAULT 'Yes' COMMENT 'Currently active?',
			  `privilege` enum('Administrator','Restricted administrator') NOT NULL DEFAULT 'Administrator' COMMENT 'Administrator level',
			  PRIMARY KEY (`username`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System administrators';
			
			CREATE TABLE `settings` (
			  `id` int NOT NULL COMMENT 'Automatic key (ignored)',
			  `frontPageHtml` TEXT NULL COMMENT 'Front page notice',
			  `copyrightUrl` varchar(255) NOT NULL DEFAULT '/' COMMENT 'Copyright URL'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Settings';
			INSERT INTO `settings` (`id`, `copyrightUrl`) VALUES (1, '/');
			
			-- Articles
			CREATE TABLE `articles` (
			  `archiveNumber` varchar(85) NOT NULL DEFAULT '',
			  `visibleOnline` enum('Yes','No') NOT NULL DEFAULT 'Yes',
			  `typeId` int DEFAULT NULL,
			  `copyExists` enum('No','Yes','Unknown') NOT NULL DEFAULT 'No',
			  `authorId` varchar(85) DEFAULT NULL,
			  `otherAuthors` mediumtext,
			  `expeditionId` varchar(85) DEFAULT NULL,
			  `objectBriefDescription` varchar(255) NOT NULL DEFAULT '',
			  `objectFurtherDescription` mediumtext,
			  `dateOfArticle` date NOT NULL,
			  `endDateOfArticle` date DEFAULT NULL,
			  `numberOfPages` varchar(85) DEFAULT NULL,
			  `additionalInformation` mediumtext,
			  `internalNotes` mediumtext,
			  `photographFilename` varchar(85) DEFAULT NULL,
			  PRIMARY KEY (`archiveNumber`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Articles';
			
			-- Authors
			CREATE TABLE `authors` (
			  `id` varchar(85) NOT NULL DEFAULT '',
			  `surname` varchar(85) NOT NULL DEFAULT '',
			  `forename` varchar(85) DEFAULT NULL,
			  `status` enum('Person','Company') DEFAULT 'Person',
			  `dateOfBirth` int DEFAULT NULL,
			  `dateOfDeath` int DEFAULT NULL,
			  `aboutText` mediumtext,
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Authors';
			
			-- Expeditions
			CREATE TABLE `expeditions` (
			  `id` varchar(85) NOT NULL DEFAULT '',
			  `name` varchar(85) NOT NULL DEFAULT '',
			  `startDate` int DEFAULT NULL,
			  `endDate` int DEFAULT NULL,
			  `shipName` varchar(85) NOT NULL DEFAULT '',
			  `leader` varchar(85) DEFAULT NULL,
			  `aboutText` mediumtext,
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Expeditions';
			
			-- Types
			CREATE TABLE `types` (
			  `id` int NOT NULL AUTO_INCREMENT,
			  `type` varchar(255) NOT NULL,
			  `documentType` varchar(255) DEFAULT NULL,
			  `manuscriptType` varchar(255) DEFAULT NULL,
			  `objectType` varchar(255) DEFAULT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Types';
			
			-- Grants
			GRANT SELECT, INSERT, UPDATE, DELETE ON `virtualshackleton`.* TO 'virtualshackleton'@'localhost';
		";
	}
	
	
	
	# Additional processing
	public function main ()
	{
		# Load required libraries
		require_once ('timedate.php');
		
		# Assign where the images are
		$this->imageDirectory = $this->baseUrl . $this->settings['imageDirectory'];
		
		# Get available types
		$this->types = $this->getTypes ();
	}
	
	
	# List of types
	private function getTypes ()
	{
		# Return the list of available types, defined in the order they should appear in the main listing
		$types = array (
			
			'expeditions' => array (
				'listing' => 'Expeditions:',
				'introductionHtml' => "<p>The following is a list of the expeditions, ordered by date. Clicking on any expedition will show the list of articles from that expedition.</p>",
				'query' => '
					SELECT DISTINCT expeditions.*
					FROM expeditions, ' . $this->settings['table'] . "
					WHERE
						    articles.expeditionId = expeditions.id
						AND articles.visibleOnline = 'Yes'
					ORDER BY startDate;
				",
				'stringFormat' => '<a href="' . $this->baseUrl . '/expeditions/%id.html">\'<strong>%shipName</strong>\' - %name (%startDate-%endDate, led by %leader)</a>',
				'typeListingReference' => 'from the <em>%name</em>',
			),
			
			'authors' => array (
				'listing' => 'Authors',
				'introductionHtml' => "<p>The following is a list of the authors, ordered by surname. Clicking on any author will show the list of articles by them.</p>",
				'query' => '
					SELECT DISTINCT authors.*
					FROM authors, ' . $this->settings['table'] . "
					WHERE
						    articles.authorId = authors.id
						AND articles.visibleOnline = 'Yes'
					ORDER BY authors.surname;
				",
				'stringFormat' => '<a href="' . $this->baseUrl . '/authors/%id.html">%forename %surname</a>',
				'typeListingReference' => 'by <em>%forename %surname</em>',
			),
			
			'articles' => array (
				'listing' => 'All catalogued articles, listed chronologically',
				'introductionHtml' => "<p>The following is a list of %totalArticles available, ordered by date:</p>",
				'query' => '
					SELECT archiveNumber,objectBriefDescription
					FROM ' . $this->settings['table'] . "
					WHERE visibleOnline = 'Yes'
					ORDER BY dateOfArticle;
				",
				'stringFormat' => '<a href="' . $this->baseUrl . '/articles/%archiveNumberMadeSafe.html">%objectBriefDescription</a>',
				'typeListingReference' => NULL,
			),
			
			// Listing of articles by type not in use
			/* 'types' => array (
				'listing' => 'Show all types',
				'introductionHtml' => "<h2>Types</h2>\n<p>The following is a list of the types of item in the archive. Clicking on any type will show the list of articles of that type.</p>",
				'query' => 'SELECT * FROM types',
				'stringFormat' => '<a href="' . $this->baseUrl . '/types/%id.html">Type: %type; Document type: %documentType; Manuscript type: %manuscriptType; Object type: %objectType</a>',
				'typeListingReference' => 'of type <em>%type - %documentType - %manuscriptType - %objectType</em>',
			), */
		);
		
		# Return the types
		return $types;
	}
	
	
	# Home page
	public function home ()
	{
		# Get the types
		if (!$typesListing = $this->listTypes ($expand = 'expeditions')) {
			return false;
		}
		
		# Show the introduction
		$html  = "\n" . '<img src="/events/exhibitions/shackleton/shackleton.jpg" class="frontpage" alt="Sir Ernest Shackleton (&copy; SPRI)" border="1" />';
		$html .= "\n" . '<p>This section of the website responds to the tremendous popular interest in the life and expeditions of the Antarctic explorer, Sir Ernest Shackleton.</p>';
		$html .= "\n" . "<p>Here you can view a selection of our archive treasures and aims to provide a scholarly resource as well as an introduction to the Institute's wealth of historical documents and artefacts.</p>";
		$html .= "\n" . '<div class="frontpagebox">';
		$html .= "\n" . $typesListing;
		$html .= "\n" . '</div>';
		$html .= "\n" . '<br />';
		$html .= "\n" . '<p>The project has been funded by <a href="http://delmas.org/" target="_blank">The Gladys Krieble Delmas Foundation</a> and the <a href="https://www.ukaht.org/" target="_blank">UK Antarctic Heritage Trust</a>.</p>';
		$html .= "\n" . '<p>This project was proposed by William Mills and implemented by Caroline Gunn with the assistance of the <a href="/contacts/webmaster.html">Webmaster</a>.</p>';
		
		# Schools resource
		if ($this->settings['frontPageHtml']) {
			$html .= "\n" . '<br />';
			$html .= $this->settings['frontPageHtml'];
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Create an HTML listing of the types
	private function listTypes ($expand = NULL)
	{
		# Get the list
		if (!$expandedList = $this->categoryListing ($expand)) {
			return false;
		}
		
		# Create then compile list items, expanding one if necessary
		$list = array ();
		foreach ($this->types as $type => $attributes) {
			$list[] = '<a href="' . $this->baseUrl . "/{$type}/\">" . $attributes['listing'] . '</a>' . ($type == $expand ? $expandedList : '');
		}
		$html = application::htmlUl ($list, 0, 'types');
		
		# Return the HTML
		return $html;
	}
	
	
	# Produce a list from an associative array based on a particular format (with %something as the substitution) and an introductory text
	private function createList ($records, $stringFormat, $introductionHtml)
	{
		# If there are no records, say so and finish
		if (empty ($records)) {
			return $html = "\n" . '<p><em>There are no records.</em></p>';
		}
		
		# Loop through each record
		$list = array ();
		foreach ($records as $recordKey => $values) {
			
			# Assign the presentation format as the HTML
			$list[$recordKey] = $stringFormat;
			
			#!# Check if this is actually necessary
			# Ensure the values are an array
			$values = application::ensureArray ($values);
			
			# Entity conversion
			foreach ($values as $key => $value) {
				$values[$key] = htmlspecialchars ($value);
			}
			
			# Replace the formatted value placeholders with the relevant value strings, cleaned; the URL make-safe version must go before the non-safety version
			foreach ($values as $key => $value) {
				$list[$recordKey] = str_replace (array ("%{$key}MadeSafe", "%$key"), array ($this->convertUrl ($value), $value), $list[$recordKey]);
			}
		}
		
		# Substitute in the total number of records where required
		$totalArticles = count ($records);
		$introductionHtml = str_replace ('%totalArticles', ($totalArticles == 1 ? "the one article" : "all {$totalArticles} articles"), $introductionHtml);
		
		# Compile the list
		$html  = "\n{$introductionHtml}";
		$html .= application::htmlUl ($list);
		
		# Return the HTML
		return $html;
	}
	
	
	# List all expeditions
	public function expeditions ()
	{
		echo $html = $this->categoryListing (__FUNCTION__, $this->types[__FUNCTION__]['introductionHtml']);
	}
	
	
	# List all authors
	public function authors ()
	{
		echo $html = $this->categoryListing (__FUNCTION__, $this->types[__FUNCTION__]['introductionHtml']);
	}
	
	
	# List all articles
	public function articles ()
	{
		echo $html = $this->categoryListing (__FUNCTION__, $this->types[__FUNCTION__]['introductionHtml']);
	}
	
	
	# List all container groupings of a particular type (e.g. show all expeditions)
	public function categoryListing ($type, $introductionHtml = false)
	{
		# Get the data
		$data = $this->databaseConnection->getData ($this->types[$type]['query']);
		
		# Create the HTML list
		$html = $this->createList ($data, $this->types[$type]['stringFormat'], $introductionHtml);
		
		# Show the HTML
		return $html;
	}
	
	
	# List articles of a particular container-grouping (e.g. all articles in a particular expedition)
	public function items ($id)
	{
		# Start the HTML
		$html = '';
		
		# Get the type
		if (!$type = (isSet ($_GET['type']) && in_array ($_GET['type'], array ('authors', 'expeditions')) ? $_GET['type'] : false)) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		#!# Need to refactor this out
		# Remove the final s
		$type = preg_replace ('/s$/', '', $_GET['type']);
		
		# Create a query to get articles, with limitation added
		$limitation = "{$type}Id = :id AND ";
		$preparedStatementValues = array ('id' => $id);
		$query = str_replace ('WHERE ', 'WHERE ' . $limitation, $this->types['articles']['query']);
		
		# Get the data or throw a 404, stating that there is no such e.g. expedition
		if (!$data = $this->databaseConnection->getData ($query, false, true, $preparedStatementValues)) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Define the string format
		$stringFormat = $this->types['articles']['stringFormat'];
		
		# Get type details
		$typeDetails = $this->getTypeDetails ($type, $id);
		
		# Clean the text
		foreach ($typeDetails as $key => $value) {
			$typeDetails[$key] = htmlspecialchars ($value);
		}
		
		# Construct the text reference to the type which is used as part of the on-page description which preceds the list
		$reference = $this->types["{$type}s"]['typeListingReference'];
		foreach ($typeDetails as $key => $value) {
			$reference = str_replace ("%{$key}", $value, $reference);
		}
		
		# Ensure that a status (only used in authors table) variable exists
		if (!isSet ($typeDetails['status'])) {$typeDetails['status'] = '';}
		
		# Create the HTML list
		$totalItems = count ($data);
		$introductionHtml  = "\n<h2>Articles {$reference}"  . ($typeDetails['status'] == 'Company' ? ' about expedition preparations' : '') . '</h2>';
		$introductionHtml .= "\n<p>The following " . ($totalItems == 1 ? 'article is' : "{$totalItems} articles are") . " $reference:</p>";
		$html .= $this->createList ($data, $stringFormat, $introductionHtml);
		
		# Add admin editing link
		if ($this->userIsAdministrator) {
			$html .= "\n<p class=\"right\"><a class=\"actions right\" href=\"{$this->baseUrl}/data/{$type}s/" . $this->doubleEncode ($id) . "/edit.html\"><img src=\"/images/icons/pencil.png\" class=\"icon\" /> Edit {$type}</a></p>";
		}
		
		# For an expedition, assign the dates then contruct information about the expedition
		if ($type == 'expedition') {
			$dates = str_replace (array ('b. ', 'd. ', '(', ')', ' '), '', timedate::dateBirthDeath ($typeDetails['startDate'], $typeDetails['endDate']));
			$expeditionHtml  = "\n" . '<div class="metadata">';
			$expeditionHtml .= "\n\t" . '<p>';
			$expeditionHtml .= "\n\t\t" . 'Expedition: ' . $typeDetails['name'] . '<br />';
			$expeditionHtml .= "\n\t\t" . 'Ship name: ' . $typeDetails['shipName'] . '<br />';
			$expeditionHtml .= "\n\t\t" . 'Leader: ' . $typeDetails['leader'] . '<br />';
			$expeditionHtml .= "\n\t\t" . 'Date: ' . $dates . '<br />';
			$expeditionHtml .= "\n\t" . '</p>';
			$expeditionHtml .= "\n" . '</div>';
		}
		
		# Add information about the type
		if (!empty ($typeDetails['aboutText'])) {
			$surroundingHtml  = "\n" . '<ul>';
			$surroundingHtml .= "\n\t" . '<li><a href="#about">About the ' . $type . '</a> (below)</li>';
			$surroundingHtml .= "\n" . '</ul>';
			$surroundingHtml .= $html;
			$surroundingHtml .= "\n" . '<h2 id="about">About the ' . $type . '</h2>';
			if ($type == 'expedition') {
				$surroundingHtml .= $expeditionHtml;
			}
			$surroundingHtml .= "\n" . application::formatTextBlock ($typeDetails['aboutText']);
			$html = $surroundingHtml;
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Get details of a type of item
	private function getTypeDetails ($type, $id)
	{
		# Get the data
		$data = $this->databaseConnection->selectOne ($this->settings['database'], "{$type}s", array ('id' => $id));
		
		# Return the data
		return $data;
	}
	
	
	# Article page
	public function article ($id)
	{
		# Start the HTML
		$html = '';
		
		# Decode the URL
		$id = $this->convertUrl ($id, $directionIsEncode = false);
		
		# End if the article number is not syntactically valid
		if (!preg_match ("|^([a-z0-9:;/ \s-]+)$|i", $id)) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Construct a query to get the full data
		$query = '
			SELECT articles.archiveNumber, types.documentType, types.manuscriptType, authors.id authorid, authors.surname, authors.forename, authors.dateOfBirth, authors.dateOfDeath, articles.otherAuthors, expeditions.id expeditionid, expeditions.name, articles.objectBriefDescription, articles.objectFurtherDescription, articles.dateOfArticle, articles.endDateOfArticle, articles.numberOfPages, articles.additionalInformation, articles.photographFilename
			FROM ' . $this->settings['table'] . "
			LEFT OUTER JOIN authors ON articles.authorId = authors.id
			LEFT OUTER JOIN expeditions ON articles.expeditionId = expeditions.id
			LEFT OUTER JOIN types ON articles.typeId = types.id
			WHERE archiveNumber = :archiveNumber
		;";
		
		# Get the data or throw an error because of a malformed SQL query
		if (!$record = $this->databaseConnection->getOne ($query, false, true, array ('archiveNumber' => $id))) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		
		# Entity conversion
		foreach ($record as $key => $value) {
			$record[$key] = htmlspecialchars ($value);
		}
		
		# Determine the name of the photograph to show if any
		$photographs = $this->getPhotographs ($record['photographFilename']);
		
		# Format the date of the article
		$dateOfArticle = timedate::formatDate ($record['dateOfArticle']);
		$endDateOfArticle = ($record['endDateOfArticle'] ? timedate::formatDate ($record['endDateOfArticle']) : '');
		
		# Define the navigation links
		$context = $this->getContextLinks ($id, $record);
		
		# Define a blank (to replace a previous/next link arrow)
		$blank = '<img src="/images/furniture/spacer.gif" alt="" border="0" />';
		
		# Define the links for next and previous
		$types = array ('expedition' => 'Articles in this expedition', 'author' => 'Articles by this author');
		$directions = array ('previous', 'next');
		foreach ($types as $type => $text) {
			foreach ($directions as $direction) {
				$link[$type][$direction] = (isSet ($context[$type][$direction]) ? '<a href="' . $this->baseUrl . '/articles/' . $this->convertUrl ($context[$type][$direction]) . '.html"><img src="/images/general/' . $direction . '.gif" alt="[' . ucfirst ($direction) . ']" /></a>' : $blank);
			}
		}
		
		# Add admin editing link
		if ($this->userIsAdministrator) {
			$html .= "\n<p class=\"right\"><a class=\"actions right\" href=\"{$this->baseUrl}/data/articles/" . $this->doubleEncode ($id) . "/edit.html\"><img src=\"/images/icons/pencil.png\" class=\"icon\" /> Edit article</a></p>";
		}
		
		# Assemble the HTML, starting with the context box
		$html .= "\n" . '<div class="context infobox">';
		$html .= "\n\t" . '<ul>';
		foreach ($types as $type => $text) {
			$html .= "\n\t\t" . '<li>' . ($record[$type . 'id'] ? $link[$type]['previous'] . ' <a href="' . $this->baseUrl . '/' . $type . 's/' . $record[$type . 'id'] . '.html">' . $text . '</a> ' . $link[$type]['next'] : '&nbsp;') . '</li>';
		}
		$html .= "\n\t" . '</ul>';
		$html .= "\n" . '</div>';
		
		# Continue with the article
		$html .= "\n" . '<h2 class="article">' . $record['objectBriefDescription'] . '</h2>';
		$html .= "\n" . '<div class="article">';
		if ($photographs) {
			$html .= $this->showImage ($photographs[0], $addBreak = false, $indicateMore = (count ($photographs) > 1));
		} else {
			$html .= "\n" . '<div class="image imagenone">';
			$html .= "\n\t" . '<p><em>No image available</em></p>';
			$html .= "\n" . '</div>';
		}
		$html .= "\n" . '<div class="metadata infobox">';
		$html .= "\n\t" . '<p>';
		$html .= "\n\t\t" . 'Expedition: ' . ($record['expeditionid'] ? '<a href="' . $this->baseUrl . '/expeditions/' . $record['expeditionid'] . '.html">' . $record['name'] . '</a>' : 'Not applicable') . '<br />';
		$html .= "\n\t\t" . 'Author: ' . ($record['authorid'] ? '<a href="' . $this->baseUrl . '/authors/' . $record['authorid'] . '.html">' . $record['forename'] . ' ' . $record['surname'] . timedate::dateBirthDeath ($record['dateOfBirth'], $record['dateOfDeath']) . '</a>' : 'Not applicable') . '<br />';
		# The date of an article in the raw data is either a year or a year+month+day (i.e. no months without a day specified)
		$html .= "\n\t\t" . 'Date of article: ' . $dateOfArticle . (($endDateOfArticle) ? " - $endDateOfArticle" : '') . '<br />';
		$html .= "\n\t" . '</p>';
		$html .= "\n\t" . '<p>';
		$html .= "\n\t\t" . 'Reference number: ' . $record['archiveNumber'] . (!empty ($record['documentType']) ? '; <acronym title="' . $this->expandAbbreviations ($record['documentType']) . '">' . $record['documentType'] . '</acronym>' : '');
		# $html .= (!empty ($record['manuscriptType']) ? "<br />\n\t" . 'Manuscript type: <acronym title="' . $this->expandAbbreviations ($record['manuscriptType']) . '">' . $record['manuscriptType'] . '</acronym>' : '');
		$html .= "\n\t" . '</p>';
		$html .= "\n" . '</div>';
		$html .= "\n" . application::formatTextBlock ($record['additionalInformation']);
		if (count ($photographs) > 1) {
			$html .= "\n" . '<div id="images">';
			$html .= "\n" . '<h3>All photographs from this article</h3>';
			$addBreak = false;
			foreach ($photographs as $photograph) {
				$html .= $this->showImage ($photograph, $addBreak);
				$addBreak = !$addBreak;
			}
			$html .= "\n" . '</div>';
		}
		$html .= "\n" . '</div>';
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to get context links from an article
	private function getContextLinks ($current, $record)
	{
		# Define the types to obtain
		$types = array ('expedition', 'author');
		
		# Get the data
		$context = array ();
		foreach ($types as $type) {
			$context[$type] = $this->getContextLinksForType ($type, $record[$type . 'id'], $current);
		}
		
		# Return the links
		return $context;
	}
	
	
	# Function to get the previous and next items from a specified type
	private function getContextLinksForType ($type, $idOfSpecifiedType, $current)
	{
		# Set up an SQL query
		$query = "
			SELECT articles.archiveNumber
			FROM {$type}s, " . $this->settings['table'] . "
			WHERE
				    {$type}s.id = :id
				AND articles.{$type}Id = {$type}s.id
				AND articles.visibleOnline = 'Yes'
			ORDER BY dateOfArticle;
		";
		$preparedStatementValues = array ('id' => $idOfSpecifiedType);
		
		# Get the data
		$data = $this->databaseConnection->getData ($query, false, true, $preparedStatementValues);
		
		# Organise the data
		$articles = array ();
		foreach ($data as $item => $attributes) {
			$articles[$item] = $attributes['archiveNumber'];
		}
		
		# Loop through and allocate the previous and next items
		$result = array ();
		foreach ($articles as $index => $item) {
			if ($item == $current) {
				if (isSet ($articles[($index - 1)])) {$result['previous'] = $articles[($index - 1)];}
				if (isSet ($articles[($index + 1)])) {$result['next'] = $articles[($index + 1)];}
				break;
			}
		}
		
		# Return the result
		return $result;
	}
	
	
	# Function to get the first photograph in a set
	private function getPhotographs ($string)
	{
		# If the string is not empty, split by comma
		$photographs = array ();
		if ($string) {
			$items = explode (',', $string);
			
			# Return the filename of the first if it exists
			foreach ($items as $item) {
				$item = trim ($item);
				$filename = trim ($_SERVER['DOCUMENT_ROOT'] . $this->imageDirectory . $item);
				if (is_readable ($filename)) {
					$photographs[] = $item;
				}
			}
		}
		
		# Return the array
		return $photographs;
	}
	
	
	# Function to show an image
	private function showImage ($image, $addBreak = false, $indicateMore = false)
	{
		# Define the link to a larger version
		#!# Seems to be doing replacement against an array
		$largerVersionLink = '<a href="' . $this->baseUrl . '/images/' . str_replace ($this->settings['mediaTypes'], '.html', $image) . '" target="_blank" class="noarrow">';
		
		# Create the HTML
		$html  = "\n" . '<div class="image">';
		
		# Determine if it's a movie
		#!# Obsolete plugin technology, so needs to be replaced to HTML5
		if (preg_match ('/.mov$/', $image)) {
			$html .= "\n\t<p><strong>Drag to rotate</strong></p>" .
			'<object classid="clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B" codebase="http://www.apple.com/qtactivex/qtplugin.cab" width="' . $this->settings['imageWidth'] . '">
				<param name="src" value="' . $this->imageDirectory . $image . '" />
				<param name="controller" value="true" />
				<param name="autoplay" value="false" />
				<param name="type" value="video/quicktime" width="' . $this->settings['imageWidth'] . '" />
				<embed src="' . $this->imageDirectory . $image . '" width="' . $this->settings['imageWidth'] . '" autoplay="false" type="video/quicktime" pluginspage="http://www.apple.com/quicktime/download/" scale="tofit" name="controller" value="true" />
			</object>';
		} else {
			$html .= "\n\t" . $largerVersionLink . '<img src="' . $this->imageDirectory . $image . '" width="' . $this->settings['imageWidth'] . '" alt="[Click for larger image in a new window]" /></a>';
			# $html .= "\n\t" . $largerVersionLink . '<img src="' . $this->settings['imageGenerationStub'] . '?' . $this->settings['imageWidth'] . ',' . $this->imageDirectory . str_replace (' ', '%20', $image) . '" width="' . $this->settings['imageWidth'] . '" alt="[Click for larger image in a new window]" /></a>';
		}
		$html .= "\n\t" . '<p class="imagecopyright"><a href="' . $this->settings['copyrightUrl'] . '">&copy; SPRI</a></p>';
		$html .= "\n\t" . '<p>' . (($indicateMore) ? '<a href="#images">More and larger images below' : $largerVersionLink . 'Larger image') . '</a></p>';
		$html .= "\n" . '</div>';
		$html .= ($addBreak ? "\n" . '<div class="linebreak"></div>' : '');
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to show an image
	public function image ($image)
	{
		# Loop through media types
		foreach ($this->settings['mediaTypes'] as $mediaType) {
			
			# Check that the requested image exists
			if (is_readable ($_SERVER['DOCUMENT_ROOT'] . $this->imageDirectory . $image . $mediaType)) {
				
				# Start the HTML
				$html = "\n" . '<p class="imagecopyright">This image below is <a href="' . $this->settings['copyrightUrl'] . '">copyright SPRI</a> and may not be reproduced without permission.</p>';
				
				# Show the correct media type
				switch ($mediaType) {
					
					case '.jpg':
						$html .= "\n" . '<div class="image imagelarge">';
						$html .= "\n\t" . '<img src="' . $this->imageDirectory . $image . $mediaType . '" border="1" />';
						$html .= "\n" . '</div>';
						break;
						
					#!# Obsolete plugin technology, so needs to be replaced to HTML5
					case '.mov':
						#!# Hard-coded width, as quicktime fails to work it out automatically
						$html .= "\n\t" .
							'<object classid="clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B" codebase="http://www.apple.com/qtactivex/qtplugin.cab" width="500" height="500">
								<param name="src" value="' . $this->imageDirectory . $image . $mediaType . '" />
								<param name="controller" value="true" />
								<param name="autoplay" value="false" />
								<param name="type" value="video/quicktime" />
								<embed width="500" height="500" src="' . $this->imageDirectory . $image . $mediaType . '" autoplay="false" type="video/quicktime" pluginspage="http://www.apple.com/quicktime/download/" controller="true" />
							</object>';
						break;
						
					default:
						// Throw error - should not have got this far
				}
				
				# Show the HTML
				echo $html;
				return true;
			}
		}
		
		# Media not found, so throw a 404 and finish
		$html = $this->page404 ();
		echo $html;
		return false;
	}
	
	
	# Function to convert a URL, e.g. / becomes ,
	private function convertUrl ($url, $directionIsEncode = true)
	{
		# Define conversions
		$conversions = array (
			'/'	=> ',',
			' '	=> '_',
		);
		
		# Define the from and to conversions
		$pure = array_keys ($conversions);
		$encoded = array_values ($conversions);
		
		# Determine the encoding direction
		list ($from, $to) = ($directionIsEncode ? array ($pure, $encoded) : array ($encoded, $pure));
		
		# Make the value URL-safe
		$url = str_replace ($from, $to, $url);
		
		# Return the converted URL
		return $url;
	}
	
	
	# Function to return the full text for an abbreviation
	private function expandAbbreviations ($abbreviation)
	{
		# Define the document types
		$abbreviations = array (
			
			# Document types
			'BJ'	=> 'Bound journal',
			'D'		=> 'Document',
			'SL'	=> "Ship's log",
			'Map'	=> 'Map',
			
			# Hover text descriptions
			'Holograph'			=> 'Original hand-written document',
			'Printed/autograph'	=> "Printed item which includes author's signature",
		);
		
		# Return the translated name if it exists, or if not the raw type
		return (isSet ($abbreviations[$abbreviation]) ? $abbreviations[$abbreviation] : $abbreviation);
	}


	# Admin editing section, substantially delegated to the sinenomine editing component
	public function editing ($attributes = array (), $deny = false, $sinenomineExtraSettings = array ())
	{
		# Define sinenomine settings
		$sinenomineExtraSettings = array (
			'int1ToCheckbox' => true,
			'datePicker' => false,		// Chrome cannot handle dates earlier than 1970, so the native HTML5 date picker cannot be used
			'hideTableIntroduction' => false,
			'tableCommentsInSelectionListOnly' => true,
			'fieldFiltering' => false,
			'truncateValues' => 80,
			'simpleJoin' => true,
		);
		
		# Define table attributes
		$attributesByTable = $this->formDataBindingAttributes ();
		$attributes = array ();
		foreach ($attributesByTable as $table => $attributesForTable) {
			foreach ($attributesForTable as $field => $fieldAttributes) {
				$attributes[] = array ($this->settings['database'], $table, $field, $fieldAttributes);
			}
		}
		
		# Define tables to deny editing for
		$deny[$this->settings['database']] = array (
			'administrators',
			'settings',
		);
		
		# Delegate to the default editor, which will echo the HTML
		parent::editing ($attributes, $deny, $sinenomineExtraSettings);
	}
	
	
	# Helper function to define the dataBinding attributes
	private function formDataBindingAttributes ()
	{
		# Define the properties, by table
		$dataBindingAttributes = array (
			'articles' => array (
				'photographFilename' => array ('directory' => $_SERVER['DOCUMENT_ROOT'] . $this->imageDirectory, 'lowercaseExtension' => true, 'allowedExtensions' => array ('jpg')),
			),
		);
		
		# Return the properties
		return $dataBindingAttributes;
	}
	
	
	# Function to urlencode a string and double-encode slashes, necessary for links into the editing, as IDs can contain /
	private function doubleEncode ($string)
	{
		# Return the string, double-encoding slashes only
		return rawurlencode (str_replace ('/', '%2f', $string));
	}
}

?>
