#
# Table structure for table 'tx_cloudinary_media'
#
CREATE TABLE tx_cloudinary_media (
  public_id text,
  public_id_hash char(40) DEFAULT '' NOT NULL,
  filename text,
  filename_hash char(40) DEFAULT '' NOT NULL,
  sha1 char(40) DEFAULT '' NOT NULL,
  modification_date int(11) DEFAULT '0' NOT NULL,

  PRIMARY KEY (public_id_hash),
  KEY filename_hash (filename_hash)
);

#
# Table structure for table 'tx_cloudinary_responsivebreakpoints'
#
CREATE TABLE tx_cloudinary_responsivebreakpoints (
  public_id text,
  public_id_hash char(40) DEFAULT '' NOT NULL,
  options_hash char(40) DEFAULT '' NOT NULL,
  breakpoints text NOT NULL,

  PRIMARY KEY (public_id_hash, options_hash)
);

#
# Table structure for table 'tx_cloudinary_processedresources'
#
CREATE TABLE tx_cloudinary_processedresources (
  public_id text,
  public_id_hash char(40) DEFAULT '' NOT NULL,
  options_hash char(40) DEFAULT '' NOT NULL,
  breakpoints text NOT NULL,

  PRIMARY KEY (public_id_hash, options_hash)
);

#
# Table structure for table 'tx_cloudinary_resource'
#
CREATE TABLE tx_cloudinary_resource (
  public_id text,
  public_id_hash char(40) DEFAULT '' NOT NULL,
  folder text,
  filename tinytext,
  format tinytext,
  version int(11) DEFAULT '0' NOT NULL,
  resource_type tinytext,
  type tinytext,
  created_at DATETIME DEFAULT '1970-01-01 00:00:00' NOT NULL,
  uploaded_at DATETIME DEFAULT '1970-01-01 00:00:00' NOT NULL,
  bytes int(11) DEFAULT '0' NOT NULL,
  backup_bytes  int(11) DEFAULT '0' NOT NULL,
  width  int(11) DEFAULT '0' NOT NULL,
  height  int(11) DEFAULT '0' NOT NULL,
  aspect_ratio double(5,2) DEFAULT '0.00000' NOT NULL,
  pixels  int(11) DEFAULT '0' NOT NULL,
  url text,
  secure_url text,
  status tinytext,
  access_mode tinytext,
  access_control tinytext,
  etag tinytext,
  file_identifier tinytext,
  file_identifier_hash char(40) DEFAULT '' NOT NULL,
  storage int(11) DEFAULT '0' NOT NULL,

  missing int(11) DEFAULT '0' NOT NULL,

  PRIMARY KEY (public_id_hash, storage),
  KEY file_identifier_hash (file_identifier_hash)
);

#
# Table structure for table 'tx_cloudinary_folder'
#
CREATE TABLE tx_cloudinary_folder (
  folder text,
  folder_hash char(40) DEFAULT '' NOT NULL,
  storage int(11) DEFAULT '0' NOT NULL,
  parent_folder text,

  missing int(11) DEFAULT '0' NOT NULL,

  PRIMARY KEY (folder_hash, storage)
);
