CREATE TABLE IF NOT EXISTS `spider_site` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) DEFAULT NULL,
  `status` enum('p','i','c','f') DEFAULT 'p',
  `crawl_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `spider_site_page` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `spider_site_id` int(11) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `status` enum('p','i','c','f') DEFAULT 'p',
  `crawl_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spider_site_id` (`spider_site_id`),
  KEY `status` (`status`),
  FOREIGN KEY (`spider_site_id`) REFERENCES `spider_site` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `spider_site_page_meta` (
  `spider_site_page_id` int(11) DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_keywords` text,
  `meta_description` text,
  KEY `spider_site_page_id` (`spider_site_page_id`),
  FOREIGN KEY (`spider_site_page_id`) REFERENCES `spider_site_page` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
