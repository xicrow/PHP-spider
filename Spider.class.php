<?php
/**
 * Cosy Spider
 * @author Jan Ebsen <xicrow@gmail.com>
 * @version 1.0.0
 * @date 2013-04-21
 */

/*
CREATE TABLE `spider_site` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) DEFAULT NULL,
  `status` enum('p','i','c','f') DEFAULT 'p',
  `crawl_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `spider_site_page` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `spider_site_id` int(11) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `status` enum('p','i','c','f') DEFAULT 'p',
  `crawl_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spider_site_id` (`spider_site_id`),
  KEY `status` (`status`),
  CONSTRAINT `FK_spider_site_page` FOREIGN KEY (`spider_site_id`) REFERENCES `spider_site` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `spider_site_page_meta` (
  `spider_site_page_id` int(11) DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_keywords` text,
  `meta_description` text,
  KEY `spider_site_page_id` (`spider_site_page_id`),
  CONSTRAINT `FK_spider_site_page_meta` FOREIGN KEY (`spider_site_page_id`) REFERENCES `spider_site_page` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
*/

class Cosy_Spider{
	/**
	 * Database handler object
	 * @access private
	 * @var PDO
	 */
	private $dbh = null;
	
	/**
	 * Options for the Spider, default values are set below
	 * @access private
	 * @var Array
	 */
	private $options = array(
		// Crawl type (all | external | internal | site | deeper)
		'crawlType' => 'all',
		// Automatically crawl next URL
		'crawlNext' => true,
		// Max pages to crawl
		'maxPages' => 10,
		// Collect META data when crawling pages (title, description, keywords)
		'collectMetaData' => false,
		// Re-crawl date-time, for re-crawling pages older than the date-time
		'recrawlDate' => null,
		// Which encoding should HTML source code be converted to
		'encoding' => 'HTML-ENTITIES',
	);
	
	/**
	 * Information for the current site
	 * @access private
	 * @var Object
	 */
	private $currentSite = null;
	
	/**
	 * Information for the current site page
	 * @access private
	 * @var Object
	 */
	private $currentSitePage = null;
	
	/**
	 * HTML source code for the current site page
	 * @access private
	 * @var String
	 */
	private $currentSitePageHtml = null;
	
	/**
	 * Counter for how many pages has been crawled
	 * @access private
	 * @var Integer
	 */
	private $pageCounter = 0;
	
	/**
	 * Array with site URL's not to crawl
	 * @access public
	 * @var Array
	 */
	public $dontCrawlSiteUrl = array();
	
	/**
	 * Display debugging information
	 * @access public
	 * @var Boolean
	 */
	public $debug = false;
	public $debugCrawler = false;
	public $debugHarvester = false;
	
	/**
	 * Constructor, optionally pass database handler and options to this
	 * @access public
	 * @param PDO $dbh
	 * @param Array $options
	 * @return true
	 */
	public function __construct(PDO $dbh = null, Array $options = array()){
		if ($dbh instanceof PDO){
			$this->setDatabaseHandler($dbh);
		}
		
		if (is_array($options)){
			$this->setOptions($options);
		}
		
		return true;
	}
	
	/**
	 * Set the database handler
	 * @access public
	 * @param PDO $dbh
	 * @return true
	 */
	public function setDatabaseHandler(PDO $dbh){
		$this->dbh = $dbh;
		
		return true;
	}
	
	/**
	 * Set array with options
	 * @access public
	 * @param Array $options
	 * @return true
	 */
	public function setOptions(Array $options){
		$this->options = array_merge($this->options, $options);
		
		return true;
	}
	
	/**
	 * Set a single option
	 * @access public
	 * @param String $key
	 * @param Mixed $value
	 * @return true
	 */
	public function setOption($key, $value){
		$key = (string)$key;
		
		if (isset($this->options[$key])){
			$this->options[$key] = $value;
			
			return true;
		}
		
		return false;
	}
	
	/**
	 * Get a single option
	 * @access public
	 * @param String $key
	 * @return true
	 */
	public function getOption($key){
		$key = (string)$key;
		
		if (isset($this->options[$key])){
			return $this->options[$key];
		}
		
		return false;
	}
	
	/**
	 * Set current URL and current URL base
	 * @access public
	 * @param String $url
	 * @return Boolean
	 */
	public function setCurrentUrl($url){
		$url = (string)$url;
		
		// Check if URL is given
		if (empty($url)){
			// Return false, no URL given
			return false;
		}
		
		// Get URL base
		$urlBase = $this->getUrlBase($url);
		
		// Check if URL base was retrieved
		if (!$urlBase){
			// Return false, unable to retrieve URL base
			return false;
		}
		
		// Get site
		$this->currentSite = $this->getSite($urlBase);
		
		// Check site status
		if (in_array($this->currentSite->status, array('c', 'f'))){
			// Return false, site has allready been crawled ("c") or has failed ("f")
			return false;
		}
		
		/*
		// Check if site is incomplete, and URL is the same as URL base
		if ($siteStatus == 'i' && $urlBase == $url){
			// Get pending pages
			$sql = '
			select url
			from spider_site_page
			where spider_site_id = :spider_site_id
				and status = :status
			order by crawl_time asc
			limit 1;';
			$stmt = $this->dbh->prepare($sql);
			$stmt->bindParam(':spider_site_id', $siteId);
			$stmt->bindParam(':status', 'p');
			$stmt->execute();
			if ($row = $stmt->fetch(PDO::FETCH_OBJ)){
				// Set page URL as the current URL
				$url = $row->url;
			}
			else{
				// Return false, no pending pages found
				return false;
			}
		}
		*/
		
		// Get site page
		$this->currentSitePage = $this->getSitePage($url);
		
		// Return true
		return true;
	}
	
	/**
	 * Start crawling
	 * @access public
	 * @return Boolean
	 */
	public function crawl(){
		// Check if current site, and current site page has been set
		if (!is_object($this->currentSite) || !is_object($this->currentSitePage)){
			// Return false, site or site page has not been set
			return false;
		}
		
		if ($this->debugCrawler){
			echo '<pre>crawl(): Crawling site page: '.$this->currentSitePage->url.'</pre>';
		}
		
		// Check if site has been crawled
		if ($this->currentSite->status == 'c'){
			if ($this->debugCrawler){
				echo '<pre>crawl(): Skipped, site is allready crawled</pre>';
			}
			
			// Crawl next site/site page
			if ($this->options['crawlNext']){
				$this->crawlNext();
			}
			
			// Return false, site has allready been crawled
			return false;
		}
		
		// Check if site page has been crawled
		if ($this->currentSitePage->status == 'c'){
			if ($this->debugCrawler){
				echo '<pre>crawl(): Skipped, site page is allready crawled</pre>';
			}
			
			// Crawl next site/site page
			if ($this->options['crawlNext']){
				$this->crawlNext();
			}
			
			// Return false, site page has allready been crawled
			return false;
		}
		
		// Check if page limit is reached
		if ($this->pageCounter >= $this->options['maxPages']){
			if ($this->debugCrawler){
				echo '<pre>crawl(): Page limit reached, stopping...</pre>';
			}
			
			// Return false, page limit reached
			return false;
		}
		
		// Attempt to get HTML source code for the URL
		$this->currentSitePageHtml = $this->getHtml($this->currentSitePage->url);
		
		// Check if HTML source code was retrieved
		if (!$this->currentSitePageHtml){
			if ($this->debugCrawler){
				echo '<pre>crawl(): Site page failed: '.$this->currentSitePage->url.'</pre>';
			}
			
			// Update status for the site page to failed ("f")
			$this->updateSitePageStatus('f');
		}
		else{
			if ($this->debugCrawler){
				echo '<pre>crawl(): Site page crawled: '.$this->currentSitePage->url.'</pre>';
			}
			
			// Update status for the site page to crawled ("c")
			$this->updateSitePageStatus('c');
			
			// Harvest links from the HTML source code
			$this->harvest($this->currentSitePageHtml);
			
			/*
			// Check if custom HTML process function has been set, and exists
			if (!empty($this->options['htmlProcessor'])){
				// Get function name
				$functionName = $this->options['htmlProcessor'];
				
				// Check if function exists, and is callable
				if (function_exists($functionName) && is_callable($functionName)){
					// Call the function
					call_user_func($functionName, $this->currentSitePageHtml);
				}
			}
			*/
		}
		
		// Clear HTML source code
		$this->currentSitePageHtml = null;
		
		// Add to page counter
		$this->pageCounter++;
		
		// Updates the status for the site (to "c" for crawled, or "i" incomplete)
		$this->updateSiteStatus();
		
		// Crawl next site/site page
		if ($this->options['crawlNext']){
			$this->crawlNext();
		}
		
		// Return true
		return true;
	}
	
	/**
	 * Continue to the next site/site page
	 * @access public
	 * @return Boolean
	 */
	public function crawlNext(){
		// Check if page limit is reached
		if ($this->pageCounter >= $this->options['maxPages']){
			if ($this->debug){
				echo '<pre>crawlNext(): Page limit reached, stopping...</pre>';
			}
			
			// Return false, page limit reached
			return false;
		}
		
		// Update site status, maybe some site pages needs to be recrawled
		$this->updateSiteStatus();
		
		// Attempt to Get next URL
		$nextUrl = $this->getNextUrl();
		
		// If next URL was found
		if ($nextUrl){
			if ($this->debug){
				echo '<pre>crawlNext(): Next URL found: '.$nextUrl.'</pre>';
			}
			
			// Set the URL
			$this->setCurrentUrl($nextUrl);
			
			// Start crawling
			$this->crawl();
			
			// Return true, happy crawling
			return true;
		}
		
		if ($this->debug){
			echo '<pre>crawlNext(): No URL found, stopping...</pre>';
		}
		
		// Return false, no URL found
		return false;
	}
	
	/**
	 * Harvest links from the HTML source code
	 * @access private
	 * @param String $html
	 * @return Boolean
	 */
	private function harvest($html){
		$html = (string)$html;
		
		// Check if HTML source code is given
		if (empty($html)){
			// Return false, no HTML given
			return false;
		}
		
		// Arrays for link, internal links, and external links
		$links = array();
		$int = array();
		$ext = array();
		
		// Get all anchor links from HTML source code
		$regEx = '#<a[^>]*?href=\"([^\"]+)\"[^>]*?>#i';
		preg_match_all($regEx, $html, $matches);
		
		// Check if any links were found
		if (!isset($matches) || !is_array($matches) || !isset($matches[1])){
			// Return false, no matches found
			return false;
		}
		
		// Get links, check for unique, and sort
		$links = $matches[1];
		$links = array_unique($links);
		sort($links);
		
		// Loop through links
		foreach ($links as $k => $v){
			// Trim off trailing slash
			$v = rtrim($v, '/');
			
			// If link is empty
			if (empty($v)){
				// Delete it from links array, and continue
				if ($this->debugHarvester){
					echo '<pre>harvest(): Removed link: '.$links[$k].' (empty)</pre>';
				}
				unset($links[$k]);
				continue;
			}
			
			// Find "&amp;" and replace with "&"
			$v = str_replace('&amp;', '&', $v);
			
			// If "javascript" link
			if (strpos($v, 'javascript:') !== false){
				// Delete it from links array, and continue
				if ($this->debugHarvester){
					echo '<pre>harvest(): Removed link: '.$links[$k].' (JS link)</pre>';
				}
				unset($links[$k]);
				continue;
			}
			
			// If "mailto" link
			if (strpos($v, 'mailto:') !== false){
				// Delete it from links array, and continue
				if ($this->debugHarvester){
					echo '<pre>harvest(): Removed link: '.$links[$k].' (mailto link)</pre>';
				}
				unset($links[$k]);
				continue;
			}
			
			// If "goto" link
			if (strpos($v, '#') !== false){
				// Delete it from links array, and continue
				if ($this->debugHarvester){
					echo '<pre>harvest(): Removed link: '.$links[$k].' (goto link)</pre>';
				}
				unset($links[$k]);
				continue;
			}
			
			// If resource link
			if (substr($v, -4) == '.jpg' ||
				substr($v, -5) == '.jpeg' ||
				substr($v, -4) == '.png' ||
				substr($v, -4) == '.gif' ||
				substr($v, -4) == '.bmp' ||
				substr($v, -4) == '.pdf' ||
				substr($v, -5) == '.docx' ||
				substr($v, -4) == '.wmv' ||
				substr($v, -4) == '.avi' ||
				substr($v, -4) == '.mpg' ||
				substr($v, -5) == '.mpeg'
				){
				// Delete it from links array, and continue
				if ($this->debugHarvester){
					echo '<pre>harvest(): Removed link: '.$links[$k].' (resource)</pre>';
				}
				unset($links[$k]);
				continue;
			}
			
			// If archive link
			if (substr($v, -4) == '.zip' ||
				substr($v, -5) == '.rar'
				){
				// Delete it from links array, and continue
				if ($this->debugHarvester){
					echo '<pre>harvest(): Removed link: '.$links[$k].' (archive)</pre>';
				}
				unset($links[$k]);
				continue;
			}
			
			// If relative link
			if (substr($v, 0, 1) == '/'){
				// Prefix link with current URL base
				$v = $this->currentSite->url.$v;
			}
			
			// If relative querystring link
			if (substr($v, 0, 1) == '?'){
				// Prefix link with current URL
				if (strpos($this->currentSitePage->url, '?') !== false){
					$v = substr($this->currentSitePage->url, 0, strpos($this->currentSitePage->url, '?')).$v;
				}
				else{
					if (substr($this->currentSitePage->url, -1, 1) == '/'){
						$v = $this->currentSitePage->url.$v;
					}
					else{
						$v = $this->currentSitePage->url.'/'.$v;
					}
				}
			}
			
			/*
			// If link does not start with "http://"
			if (substr($v, 0, 7) != 'http://'){
				// Check if URL differs from URL base
				if ($this->currentSitePage->url == $this->currentSite->url){
					// Prepend base URL
					$v = $this->currentSite->url.'/'.ltrim($v, '/');
				}
				else{
					// Prepend URL
					$v = (substr($this->currentSitePage->url, 0, strrpos($this->currentSitePage->url, '/'))).'/'.ltrim($v, '/');
				}
			}
			*/
			
			// If link does not start with "http://"
			if (substr($v, 0, 7) != 'http://'){
				// Delete it from links array, and continue
				if ($this->debugHarvester){
					echo '<pre>harvest(): Removed link: '.$links[$k].' (no protocol)</pre>';
				}
				unset($links[$k]);
				continue;
			}
			
			// Remove "www." to avoid duplicates
			$v = str_replace('www.', '', $v);
			
			// If link is current base URL
			if ($v == $this->currentSite->url){
				// Delete it from links array, and continue
				if ($this->debugHarvester){
					echo '<pre>harvest(): Removed link: '.$links[$k].' (base URL)</pre>';
				}
				unset($links[$k]);
				continue;
			}
			
			// If link is internal
			if (substr($v, 0, strlen($this->currentSite->url)) == $this->currentSite->url){
				// Save to internal links, and delete it from links array
				if ($this->debugHarvester){
					echo '<pre>harvest(): Saved as internal: '.$links[$k].'</pre>';
				}
				$int[] = $v;
				unset($links[$k]);
			}
			else{
				// Save to external links, and delete it from links array
				if ($this->debugHarvester){
					echo '<pre>harvest(): Saved as external: '.$links[$k].'</pre>';
				}
				$ext[] = $v;
				unset($links[$k]);
			}
		}
		
		// Check internal and external for unique
		$int = array_unique($int);
		$ext = array_unique($ext);
		
		// If any internal links were found
		if (count($int) > 0){
			// Loop through internal links
			foreach ($int as $sitePageUrl){
				// Check if site page allready exists
				$sql = '
				select id
				from spider_site_page
				where url = :url
				limit 1;';
				$stmt = $this->dbh->prepare($sql);
				$stmt->bindParam(':url', $sitePageUrl);
				$stmt->execute();
				if (!$row = $stmt->fetch(PDO::FETCH_OBJ)){
					// Insert site page
					$sql = '
					insert into spider_site_page set
					spider_site_id = :spider_site_id,
					url = :url,
					status = :status,
					crawl_time = :crawl_time;';
					$stmt = $this->dbh->prepare($sql);
					$stmt->bindParam(':spider_site_id', $this->currentSite->id);
					$stmt->bindParam(':url', $sitePageUrl);
					$stmt->bindValue(':status', 'p');
					$stmt->bindValue(':crawl_time', date('Y-m-d H:i:s'));
					$stmt->execute();
				}
			}
		}
		
		// If any external links were found
		if (count($ext) > 0){
			// Loop through external links
			foreach ($ext as $sitePageUrl){
				$siteUrl = $this->getUrlBase($sitePageUrl);
				$siteId = null;
				
				// Check if site allready exists
				$sql = '
				select id
				from spider_site
				where url = :url
				limit 1;';
				$stmt = $this->dbh->prepare($sql);
				$stmt->bindParam(':url', $siteUrl);
				$stmt->execute();
				if ($row = $stmt->fetch(PDO::FETCH_OBJ)){
					// Get site ID
					$siteId = $row->id;
				}
				else{
					// Insert site
					$sql = '
					insert into spider_site set
					url = :url,
					status = :status,
					crawl_time = :crawl_time;';
					$stmt = $this->dbh->prepare($sql);
					$stmt->bindParam(':url', $siteUrl);
					$stmt->bindValue(':status', 'p');
					$stmt->bindValue(':crawl_time', date('Y-m-d H:i:s'));
					$stmt->execute();
					
					// Get site ID
					$siteId = $this->dbh->lastInsertId();
				}
				
				if (!is_null($siteId)){
					// Check if site page allready exists
					$sql = '
					select id
					from spider_site_page
					where url = :url
					limit 1;';
					$stmt = $this->dbh->prepare($sql);
					$stmt->bindParam(':url', $sitePageUrl);
					$stmt->execute();
					if (!$row = $stmt->fetch(PDO::FETCH_OBJ)){
						// Insert site page
						$sql = '
						insert into spider_site_page set
						spider_site_id = :spider_site_id,
						url = :url,
						status = :status,
						crawl_time = :crawl_time;';
						$stmt = $this->dbh->prepare($sql);
						$stmt->bindParam(':spider_site_id', $siteId);
						$stmt->bindParam(':url', $sitePageUrl);
						$stmt->bindValue(':status', 'p');
						$stmt->bindValue(':crawl_time', date('Y-m-d H:i:s'));
						$stmt->execute();
					}
				}
			}
		}
		
		// Return true, links has been harvested and saved
		return true;
	}
	
	/**
	 * Get or create a site, from the given URL
	 * @access private
	 * @param String $url
	 * @return Object
	 */
	private function getSite($url){
		$url = (string)$url;
		
		// Set default site object
		$site = (object)array(
			'id' => null,
			'url' => $url,
			'status' => 'p',
			'crawl_time' => date('Y-m-d H:i:s'),
		);
		
		// Check if site exists
		$sql = '
		select id, status, crawl_time
		from spider_site
		where url = :url
		limit 1;';
		$stmt = $this->dbh->prepare($sql);
		$stmt->bindParam(':url', $site->url);
		$stmt->execute();
		if ($row = $stmt->fetch(PDO::FETCH_OBJ)){
			// Set values from existing site
			$site->id = $row->id;
			$site->status = $row->status;
			$site->crawl_time = $row->crawl_time;
		}
		else{
			// Site does not exist, create it
			$sql = '
			insert into spider_site set
			url = :url,
			status = :status,
			crawl_time = :crawl_time;';
			$stmt = $this->dbh->prepare($sql);
			$stmt->bindParam(':url', $site->url);
			$stmt->bindParam(':status', $site->status);
			$stmt->bindParam(':crawl_time', $site->crawl_time);
			$stmt->execute();
			
			// Save site ID
			$site->id = $this->dbh->lastInsertId();
		}
		
		// Return site
		return $site;
	}
	
	/**
	 * Get or create a site page, from the given URL
	 * @access private
	 * @param String $url
	 * @return Object
	 */
	private function getSitePage($url){
		$url = (string)$url;
		
		// Set default site page object
		$sitePage = (object)array(
			'id' => null,
			'spider_site_id' => $this->currentSite->id,
			'url' => $url,
			'status' => 'p',
			'crawl_time' => date('Y-m-d H:i:s'),
		);
		
		// Check if site page exists
		$sql = '
		select id, status, crawl_time
		from spider_site_page
		where url = :url
		limit 1;';
		$stmt = $this->dbh->prepare($sql);
		$stmt->bindParam(':url', $sitePage->url);
		$stmt->execute();
		if ($row = $stmt->fetch(PDO::FETCH_OBJ)){
			// Set values from existing site page
			$sitePage->id = $row->id;
			$sitePage->status = $row->status;
			$sitePage->crawl_time = $row->crawl_time;
		}
		else{
			// Site page does not exist, create it
			$sql = '
			insert into spider_site_page set
			spider_site_id = :spider_site_id,
			url = :url,
			status = :status,
			crawl_time = :crawl_time;';
			$stmt = $this->dbh->prepare($sql);
			$stmt->bindParam(':spider_site_id', $sitePage->spider_site_id);
			$stmt->bindParam(':url', $sitePage->url);
			$stmt->bindParam(':status', $sitePage->status);
			$stmt->bindParam(':crawl_time', $sitePage->crawl_time);
			$stmt->execute();
			
			// Save site page ID
			$sitePage->id = $this->dbh->lastInsertId();
		}
		
		// Return site page
		return $sitePage;
	}
	
	/**
	 * Update status on current site
	 * @return boolean
	 * @access private
	 */
	private function updateSiteStatus(){
		// Check if re-crawl date is set
		if (!empty($this->options['recrawlDate']) && strtotime($this->options['recrawlDate'])){
			$recrawlDateTime = date('Y-m-d H:i:s', strtotime($this->options['recrawlDate']));
			
			// Update site page to status "p" (pending), for thoose needing to be re-crawled
			$sql = '
			update spider_site_page set
			status = :status1
			where status = :status2
				and crawl_time < :crawl_time;';
			$stmt = $this->dbh->prepare($sql);
			$stmt->bindValue(':status1', 'p');
			$stmt->bindValue(':status2', 'c');
			$stmt->bindParam(':crawl_time', $recrawlDateTime);
			$stmt->execute();
		}
		
		// Check if any site pages are pending
		$sql = '
		select id
		from spider_site_page
		where spider_site_id = :spider_site_id
			and status = :status
		limit 1;';
		$stmt = $this->dbh->prepare($sql);
		$stmt->bindParam(':spider_site_id', $this->currentSite->id);
		$stmt->bindValue(':status', 'p');
		$stmt->execute();
		if ($row = $stmt->fetch(PDO::FETCH_OBJ)){
			// Update site status to "i" (incomplete)
			$sql = '
			update spider_site set
			status = :status
			where id = :id;';
			$stmt = $this->dbh->prepare($sql);
			$stmt->bindValue(':status', 'i');
			$stmt->bindParam(':id', $this->currentSite->id);
			$stmt->execute();
		}
		else{
			// Update site status to "c" (crawled)
			$sql = '
			update spider_site set
			status = :status
			where id = :id;';
			$stmt = $this->dbh->prepare($sql);
			$stmt->bindValue(':status', 'c');
			$stmt->bindParam(':id', $this->currentSite->id);
			$stmt->execute();
		}
		
		// Return true, site status has been updated
		return true;
	}
	
	/**
	 * Save site page
	 * @access private
	 * @param String $status
	 * @return boolean
	 */
	private function updateSitePageStatus($status = 'c'){
		$status = (string)$status;
		
		// Should we collect META data
		if ($this->options['collectMetaData'] && !empty($this->currentSitePageHtml)){
			// Get page meta title
			$metaTitle = '';
			$regEx = '#<title>(.+)<\/title>#siU';
			if (preg_match($regEx, $this->currentSitePageHtml, $matches)){
				$metaTitle = $matches[1];
			}
			
			// Get page meta keywords
			$metaKeywords = '';
			$regEx = '#<meta\s+name=[\'"]??keywords[\'"]??\s+content=[\'"]??(.+)[\'"]??\s*\/?>#siU';
			if (preg_match($regEx, $this->currentSitePageHtml, $matches)){
				$metaKeywords = $matches[1];
			}
			
			// Get page meta description
			$metaDescription = '';
			$regEx = '#<meta\s+name=[\'"]??description[\'"]??\s+content=[\'"]??(.+)[\'"]??\s*\/?>#siU';
			if (preg_match($regEx, $this->currentSitePageHtml, $matches)){
				$metaDescription = $matches[1];
			}
		}
		
		// Update site page
		$sql = '
		update spider_site_page set
		status = :status,
		crawl_time = :crawl_time
		where id = :id;';
		$stmt = $this->dbh->prepare($sql);
		$stmt->bindParam(':status', $status);
		$stmt->bindValue(':crawl_time', date('Y-m-d H:i:s'));
		$stmt->bindParam(':id', $this->currentSitePage->id);
		$stmt->execute();
		
		// Check if META data has been retrieved
		if (isset($metaTitle) && isset($metaKeywords) && isset($metaDescription)){
			// Save META data for the page
			$sql = '
			select spider_site_page_id
			from spider_site_page_meta
			where spider_site_page_id = :spider_site_page_id
			limit 1;';
			$stmt = $this->dbh->prepare($sql);
			$stmt->bindParam(':spider_site_page_id', $this->currentSitePage->id);
			$stmt->execute();
			if ($row = $stmt->fetch(PDO::FETCH_OBJ)){
				// Update META data
				$sql = '
				update spider_site_page_meta set
				meta_title = :meta_title,
				meta_keywords = :meta_keywords,
				meta_description = :meta_description
				where spider_site_page_id = :spider_site_page_id;';
				$stmt = $this->dbh->prepare($sql);
				$stmt->bindParam(':meta_title', $metaTitle);
				$stmt->bindParam(':meta_keywords', $metaKeywords);
				$stmt->bindValue(':meta_description', $metaDescription);
				$stmt->bindParam(':spider_site_page_id', $$this->currentSitePage->id);
				$stmt->execute();
			}
			else{
				// Insert META data
				$sql = '
				insert into spider_site_page_meta set
				spider_site_page_id = :spider_site_page_id,
				meta_title = :meta_title,
				meta_keywords = :meta_keywords,
				meta_description = :meta_description;';
				$stmt = $this->dbh->prepare($sql);
				$stmt->bindParam(':spider_site_page_id', $this->currentSitePage->id);
				$stmt->bindParam(':meta_title', $metaTitle);
				$stmt->bindParam(':meta_keywords', $metaKeywords);
				$stmt->bindValue(':meta_description', $metaDescription);
				$stmt->execute();
			}
		}
		
		// Return true, site page has been suceessfully saved
		return true;
	}
	
	/**
	 * Get URL base from a URL
	 * @access private
	 * @param String $url
	 * @return Mixed
	 */
	private function getUrlBase($url){
		$url = (string)$url;
		
		// Check if URL is given
		if (empty($url)){
			return false;
		}
		
		// Parse the URL
		$urlParts = parse_url($url);
		
		// Check if URL was successfully parsed
		if (!isset($urlParts['scheme']) || !isset($urlParts['host']) || $urlParts['scheme'] != 'http'){
			// Return false, invalid URL
			return false;
		}
		
		// Build the URL base
		$urlBase = $urlParts['scheme'];
		$urlBase.= '://';
		$urlBase.= str_replace('www.', '', $urlParts['host']);
		
		// Return the URL base
		return $urlBase;
	}
	
	/**
	 * Get HTML source code from the current URL
	 * @access private
	 * @param String $url
	 * @return mixed
	 */
	private function getHtml($url){
		$url = (string)$url;
		
		// Check if cURL is avaliable
		if (function_exists('curl_init')){
			// Initialize cURL
			$ch = curl_init();
			
			// Set URL
			curl_setopt($ch, CURLOPT_URL, $url);
			// Follow redirects
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			// Max number of redirects
			curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
			// Include header in output
			curl_setopt($ch, CURLOPT_HEADER, false);
			// Max timeout in seconds
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			// The user agent
			curl_setopt($ch, CURLOPT_USERAGENT, 'Cosy Spider');
			// Return data
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			
			// Execute cURL request
			$html = curl_exec($ch);
			
			// Get cURL information
			$info = curl_getinfo($ch);
			
			// Close cURL
			curl_close($ch);
			
			// Check response code
			if ($info['http_code'] != 200){
				// Return false, no "200 OK" header in the reponse
				return false;
			}
			
			// Clear variables
			unset($ch, $info);
		}
		else{
			// Attempt to get HTML source code
			$html = @file_get_contents($url);
		}
		
		// Check if HTML source code was retrieved
		if (empty($html)){
			// Return false, no HTML was retrieved
			return false;
		}
		
		// Detect encoding for the HTML source code, and convert to specified encoding
		mb_detect_order('ASCII,UTF-8,ISO-8859-1,windows-1252,iso-8859-15');
		$encoding = mb_detect_encoding($html);
		$html = mb_convert_encoding($html, $this->options['encoding'], $encoding);
		
		// Return HTML source code
		return $html;
	}
	
	/**
	 * Get next URL to crawl
	 * @access private
	 * @return String
	 */
	private function getNextUrl(){
		// If crawl type is: deeper
		if (in_array($this->options['crawlType'], array('deeper')) && !empty($this->currentSite->id) && !empty($this->currentSitePage->id)){
			// Save start URL to crawl deeper from
			static $crawlDeeperUrl;
			if (empty($crawlDeeperUrl)){
				$crawlDeeperUrl = $this->currentSitePage->url;
			}
			
			// Get next pending page deeper than the $crawlDeeperUrl, in the current site
			$sql = '
			select url
			from spider_site_page
			where spider_site_id = :spider_site_id
				and url like :url
				and status = :status
			order by id asc
			limit 1;';
			$stmt = $this->dbh->prepare($sql);
			$stmt->bindParam(':spider_site_id', $this->currentSite->id);
			$stmt->bindValue(':url', $crawlDeeperUrl.'%');
			$stmt->bindValue(':status', 'p');
			$stmt->execute();
			if ($row = $stmt->fetch(PDO::FETCH_OBJ)){
				return $row->url;
			}
		}
		
		// If crawl type is: site
		if (in_array($this->options['crawlType'], array('site')) && !empty($this->currentSite->id)){
			// Get next pending page, in the current site
			$sql = '
			select url
			from spider_site_page
			where spider_site_id = :spider_site_id
				and status = :status
			limit 1;';
			$stmt = $this->dbh->prepare($sql);
			$stmt->bindParam(':spider_site_id', $this->currentSite->id);
			$stmt->bindValue(':status', 'p');
			$stmt->execute();
			if ($row = $stmt->fetch(PDO::FETCH_OBJ)){
				return $row->url;
			}
		}
		
		// If crawl type is: all | internal
		if (in_array($this->options['crawlType'], array('all', 'internal'))){
			// Get next pending site page
			$sql = '
			select spider_site_page.url
			from spider_site
			join spider_site_page on spider_site_page.spider_site_id = spider_site.id
			where spider_site.status = :spider_site_status
				and spider_site_page.status = :spider_site_page_status';
			if (count($this->dontCrawlSiteUrl)){
				$sql.= '
				and spider_site.url not in ("'.implode('", "', $this->dontCrawlSiteUrl).'")';
			}
			$sql.= '
			order by spider_site.id asc, spider_site_page.id asc
			limit 1;';
			$stmt = $this->dbh->prepare($sql);
			$stmt->bindValue(':spider_site_status', 'i');
			$stmt->bindValue(':spider_site_page_status', 'p');
			$stmt->execute();
			if ($row = $stmt->fetch(PDO::FETCH_OBJ)){
				return $row->url;
			}
		}
		
		// If crawl type is: all | external
		if (in_array($this->options['crawlType'], array('all', 'external'))){
			// Get next pending site
			$sql = '
			select url
			from spider_site
			where status = :status';
			if (count($this->dontCrawlSiteUrl)){
				$sql.= '
				and spider_site.url not in ("'.implode('", "', $this->dontCrawlSiteUrl).'")';
			}
			$sql.= '
			order by id asc
			limit 1;';
			$stmt = $this->dbh->prepare($sql);
			$stmt->bindValue(':status', 'p');
			$stmt->execute();
			if ($row = $stmt->fetch(PDO::FETCH_OBJ)){
				return $row->url;
			}
		}
		
		// Return false, no URL found
		return false;
	}
}
?>