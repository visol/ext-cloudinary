# Cloudinary Integration - FAL Driver

A TYPO3 extension that connect TYPO3 with [Cloudinary](cloudinary.com) services
by the means of a **Cloudinary Driver for FAL**.
The extension also provides various View Helpers to render images on the Frontend.
Cloudinary is a service provider dealing with images and videos. 
It offers various services among others:

* CDN for fast images and videos delivering
* Manipulation of images and videos such as cropping, resizing and much more...
* DAM functionalities where images can be tagged and metadata edited

## Compatibility and Maintenance

This package is currently maintained for the following versions:

| TYPO3 Version | Package Version | Branch  | Maintained    |
|---------------|-----------------|---------|---------------|
| TYPO3 11.5.x  | 4.x             | master  | Yes           |
| TYPO3 11.5.x  | 3.x             | -       | No            |
| TYPO3 11.5.x  | 0.x             | -       | No            |

Installation
============

The extension should be done by Composer

```
composer require visol/cloudinary
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


![](Documentation/driver-configuration-02.png)

Once the record is saved, you should see a message telling the connection could be successfully established. 
You can now head to the File module list. 
Notice the first time you click on a folder in the File list module, 
it will take some time since the images must be fetched and put into the cloudinary cache.

Notice you can also use environment variable to configure the storage.
The environment variable should be surrounded by %. Example `%env(BUCKET_NAME%)`

![](Documentation/driver-configuration-01.png)

Cloudinary integration as file picker
-------------------------------------

The extension is providing an integration with Cloudinary so that the editor can directly interact with the cloudinary library in the backend.
When clicking on a button, a modal window will open up displaying the Cloudinary files directly. 
From there, the files can be inserted directly as file references. 

![](Documentation/backend-cloudinary-integration-01.png)

To enable this button, we should first configure the extension settings to display the
desired button and storage.

![](Documentation/extension-configuration-01.png)


We can even take it a step further by enabling auto-login.
A new field called authenticationEmail has been added to the storage configuration.
By providing a configured email in Cloudinary, we can automatically log
in when clicking on the button. Magic!

![](Documentation/driver-configuration-03.png)


Configuration TCEFORM
---------------------

We can configure the form in the backend to hide the default TYPO3 button,
thus limiting backend user interaction with the Cloudinary library. 
Here is an example of such a configuration:

```
TCEFORM {
    pages {
        media {
            config {
                appearance {
                    fileUploadAllowed = 0
                    fileByUrlAllowed = 0
                    elementBrowserEnabled = 0
                }
            }
        }
    }
}
```

Logging
-------

For the debug purposes Cloudinary API calls are logged to better track and understand how and when the API is called.
It might be useful to check the log file in case of a low response time in the BE.

```
tail -f public/typo3temp/var/logs/cloudinary.log
```

To decide: we now have log level INFO. We might consider "increasing" the level to "DEBUG".

Caveats and troubleshooting
----------------------------

* **Free** Cloudinary account allows 500 API request per day 
* This cloudinary FAL driver is currently **limited to images**.

ViewHelpers
-----------

The extension provides ViewHelpers that can be used like that:

1. Output an images and its source-set.

```
<html xmlns:c="http://typo3.org/ns/Visol/Cloudinary/ViewHelpers">
    <c:cloudinaryImage image="{file}"/>
</html>
```

This will produces the following output:

```
<img sizes="(max-width: 768px) 100vw, 768px" 
     srcset="https://res.cloudinary.com/fabidule/image/upload/f_auto,fl_lossy,q_auto,c_crop/c_scale,w_768/v1570098754/sample/animals/cat.jpg 768w,
            https://res.cloudinary.com/fabidule/image/upload/f_auto,fl_lossy,q_auto,c_crop/c_scale,w_553/v1570098754/sample/animals/cat.jpg 553w,
            https://res.cloudinary.com/fabidule/image/upload/f_auto,fl_lossy,q_auto,c_crop/c_scale,w_100/v1570098754/sample/animals/cat.jpg 100w" 
    src="https://res.cloudinary.com/fabidule/image/upload/v1570098754/sample/animals/cat.jpg" />
```

2. Generate an array of variants that can be iterated.

```
<html xmlns:c="http://typo3.org/ns/Visol/Cloudinary/ViewHelpers">
    <c:cloudinaryImageData image="{file}">
        <f:debug>{responsiveImageData}</f:debug>
    </c:cloudinaryImageData>
</html>
```

CLI Command
-----------

Move bunch of images from a local storage to a cloudinary storage.

**CAUTIOUS!**
1. Moving means: we are "manually" uploading a file (skipping FAL API)
to the Cloudinary storage and deleting the one from the local storage (rm -f FILE) 
Finally we are changing the `sys_file.storage value` to the cloudinary storage.
The file uid will be kept!
  
```shell script
./vendor/bin/typo3 cloudinary:move 1 2
# param 1: the source storage uid (local)
# param 2: the target storage uid (cloudinary)

# Will all parameters
./vendor/bin/typo3 cloudinary:move 1 2 --base-url=https://domain.tld/fileadmin --folder-filter=folderFilter

# --base-url: the base URL where to download files (the file will be downloaded directly from the remote)
# --filter: a possible filter, ex. %.youtube, /foo/bar/%
# --filter-file-type: add a possible filter for file type as defined by FAL (e.g 1,2,3,4,5)
# --limit: add a possible offset, limit to restrain the number of files. (eg. 0,100)
# --yes: do not ask question. Just do it!
# --silent: be quiet!
```

After "moving" files you should fix the jpeg extension for the Cloudinary storage by running
the command below.
It is worth mentioning that Cloudinary stripped the file extension for images. For instance
a file `image.jpg` or `image.jpeg` uploaded on Cloudinary will be stored as `image`
without the file extension. By inspecting the file, we will see that Cloudinary only consider 
the "jpg" extension. Consequently `image.jpeg` will be served as `image.jpg`. 
This has an implication for us. Record from table `sys_file` must be adjusted and occurrences
`jpeg` in file identifier or file name must be changed to `jpg` for consistency.

```shell script
./vendor/bin/typo3 cloudinary:fix 2
# where "2" is the target storage uid (cloudinary)
```

Tip: to sync / upload a bunch of files, you can use the Cloudinary CLI which is convenient to upload
many resources at once.

```bash
cld sync --push localFolder remoteFolder
```

The extension provides also a tool to copy a bunch of files (restricted to images) from one storage to another. 
This can be achieved with this command:

```shell script
./vendor/bin/typo3 cloudinary:copy 1 2         
# where 1 is the source storage (local)
# and 2 is the target storage (cloudinary)
 
# Output:
Copying 64 files from storage "fileadmin/ (auto-created)" (1) to "Cloudinary Storage (fabidule)" (2)
Copying /introduction/images/typo3-book-backend-login.png
Copying /introduction/images/content/content-quote.png
...
Number of file copied: 64
``` 

For your information a set of acceptance tests has been implemented to validate the functionnalities
of the driver.

```bash
./vendor/bin/typo3 cloudinary:tests fabidule:1234:ABCD 
```

Development tools
-----------------

Type command `make` at the source of the extension to display utility commands related to code formatting. 

```
Usage:
 make [target]

Available targets:
 help:            Help
 lint:           Display formatting issues in detail
 lint-summary:   Display a summary of formatting issues
 lint-fix:       Automatically fix code formatting issues
```

Web Hook
--------


Whenever uploading or editing a file in the cloudinary library, you can configure in the cloudinary settings a URL to 
be called as a web hook. This is recommended to keep the data consistent between Cloudinary and TYPO3. When overriding 
or moving a file across folders, cloudinary will inform TYPO3 that something has changed.

It will basically:

* invalidate the processed files
* invalidate the page cache where the file is involved.


```shell script
https://domain.tld/?type=1573555440
```

This, however, will not work out of the box and requires some manual configuration. 
Refer to the file ext:cloudinary/Configuration/TypoScript/setup.typoscript where we define a custom type. 
This is an example TypoScript file. Make sure that the file is loaded, and that you have defined a storage UID. 
Your system may contain multiple Cloudinary storages, and each web hook must refer to its own Cloudinary storage.
Eventually you will end up having as many config as you have cloudinary storage.

Source of inspiration
---------------------

Adapter for php flysystem for Cloudinary

https://github.com/flownative/flow-google-cloudstorage
