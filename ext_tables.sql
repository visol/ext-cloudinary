#
# Table structure for table 'tx_cloudinary_media'
#
CREATE TABLE tx_cloudinary_media (
  public_id text,
  filename text,
  sha1 char(40) DEFAULT '' NOT NULL,
  modification_date int(11) DEFAULT '0' NOT NULL,

  PRIMARY KEY (public_id),
  KEY filename (filename),
);

#
# Table structure for table 'tx_cloudinary_responsivebreakpoints'
#
CREATE TABLE tx_cloudinary_responsivebreakpoints (
  public_id text,
  options_hash char(40) DEFAULT '' NOT NULL,
  breakpoints text NOT NULL,

  PRIMARY KEY (public_id, options_hash),
);
