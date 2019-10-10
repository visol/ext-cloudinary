# Cloudinary Integration

A TYPO3 extension that connect TYPO3 with [Cloudinary](cloudinary.com) services
by the means of a **Cloudinary Driver for FAL**.
The extension also provides various View Helpers to render images on the Frontend.
Cloudinary is a service provider dealing with images and videos. 
It offers various services among other:

* CDN for fast images and videos delivering
* Manipulation of images and videos such as cropping, resizing and much more...
* DAM functionalities where images can be tagged and metadata edited

Installation
============

The extension should be done by Composer

```
composer require sinso/cloudinary
```

Note that the extension will require the library `cloudinary/cloudinary_php` and 
be automatically downloaded into `vendor`.


Configuration
=============

If it is not already the case, create an account on [Cloudinary](https://cloudinary.com/users/register/free) at first.
Once the extension is installed, we should create a [file storage](https://docs.typo3.org/m/typo3/reference-coreapi/master/en-us/ApiOverview/Fal/Administration/Storages.html). 

For a new "file storage" record, then:

* Pick the **Cloudinary** driver in the driver dropdown menu.
* Fill in the requested fields. Password and secrets can be found from the [Cloudinary Console](https://cloudinary.com/console).
* **Important!** Configure the "folder for manipulated and temporary images" on a local driver where we have a **writeable** processed folder.


![](Documentation/driver-configuration-02.png)

Once the record is saved, you should see a message telling the connection could be successfully established. 
You can now head to the File module list. 
Notice the first time you click on a folder in the File list module, 
it will take some time since the images must be fetched and downloaded for local processing.

![](Documentation/driver-configuration-01.png)

Logging
-------

For the debug purposes Cloudinary API calls are logged to better track and understand how and when the API is called.
It might be useful to check the log file in case of a low response time in the BE.

```
tail -f public/typo3temp/var/logs/cloudinary.log
```

TODO: we now have log level INFO. We might consider "increasing" the level to "DEBUG".

Caveats and trouble shooting
----------------------------

* As said above, the first time a folder is clicked in the File list module, 
 images must be retrieved from Cloudinary to be locally processed and thumbnails generated.
 Be patient if you have many images to display.
 * Free Cloudinary account allows 500 API request per day 
* The cloudinary FAL driver is currently **limited to images**.

## Source of inspiration

https://github.com/carlosocarvalho/flysystem-cloudinary/blob/master/src/CloudinaryAdapter.php
