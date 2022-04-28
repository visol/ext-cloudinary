#
# Table structure for table 'tt_content'
#
CREATE TABLE tt_content (
  tx_cloudinary_resources text,
);

#
# Table structure for table 'tx_cloudinary_explicit_data_cache'
#
CREATE TABLE tx_cloudinary_explicit_data_cache (
  storage int(11) DEFAULT '0' NOT NULL,
  public_id text,
  public_id_hash char(40) DEFAULT '' NOT NULL,
  options text NOT NULL,
  options_hash char(40) DEFAULT '' NOT NULL,
  explicit_data text NOT NULL,

  tstamp int(11) unsigned DEFAULT '0' NOT NULL,
  crdate int(11) unsigned DEFAULT '0' NOT NULL,

  PRIMARY KEY (storage, public_id_hash, options_hash)
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

  storage int(11) DEFAULT '0' NOT NULL,
  missing int(11) DEFAULT '0' NOT NULL,

  PRIMARY KEY (public_id_hash, storage)
);

#
# Table structure for table 'tx_cloudinary_folder'
#
CREATE TABLE tx_cloudinary_folder (
  folder text,
  folder_hash char(40) DEFAULT '' NOT NULL,
  parent_folder text,

  storage int(11) DEFAULT '0' NOT NULL,
  missing int(11) DEFAULT '0' NOT NULL,

  PRIMARY KEY (folder_hash, storage)
);
