/*jshint -W033, -W003, -W116, -W069 */
/*globals define:false, cloudinary:false, cloudinaryCredentials:false, TYPO3:false */
define([
  'jquery',
  'nprogress',
  'TYPO3/CMS/Backend/Utility/MessageUtility',
  'TYPO3/CMS/Backend/Modal',
  'TYPO3/CMS/Backend/Severity',
], function ($, NProgress, MessageUtility, Modal, Severity) {

  let irreNewTimout;
  let irreToggleTimout;

  // Click "new" irre
  $('.t3js-create-new-button').click(function(e) {
    const numberOfIrreObjects = $(this).parents('.form-group').find('.form-irre-object').length;
    irreNewTimout = setTimeout(isNewIrreElementReady, 300, this, numberOfIrreObjects)
  })

  // Detect if the "new" irre is ready
  function isNewIrreElementReady(element, numberOfIrreObjects) {

    const _numberOfIrreObjects = $(element).parents('.form-group').find('.form-irre-object').length;

    if (_numberOfIrreObjects > numberOfIrreObjects) {
      clearTimeout(irreNewTimout)
      initializeCloudinaryButtons()
    } else {
      irreToggleTimout = setTimeout(isNewIrreElementReady, 100, element, numberOfIrreObjects)
    }
  }

  // Click "toggle" irre box
  $('.form-irre-header-cell').click(function(e) {
    irreToggleTimout = setTimeout(isEditIrreElementReady, 300, this)
  })

  // Detect if the "toggle" irre is ready
  function isEditIrreElementReady(element) {

    // Detect if the element is ready to be initialized
    const childElement = $(element).parents('div[data-object-uid]').find('.panel-collapse .tab-content')
    if (childElement.length) {
      clearTimeout(irreToggleTimout)
      initializeCloudinaryButtons()
    } else {
      irreToggleTimout = setTimeout(isEditIrreElementReady, 100, element)
    }
  }

  function initializeCloudinaryButtons () {

    $('.btn-cloudinary-media-library[data-is-initialized="0"]').map((index, element) => {

      const cloudinaryCredentials = Array.isArray($(element).data('cloudinaryCredentials'))
        ? $(element).data('cloudinaryCredentials')
        : []

      cloudinaryCredentials.map((credential) => {

        // Render the cloudinary button
        const mediaLibrary = cloudinary.createMediaLibrary(
          {
            cloud_name: credential.cloudName,
            api_key: credential.apiKey,
            username: credential.username,
            timestamp: credential.timestamp,
            signature: credential.signature,
            button_class:
              'btn btn-default open-btn mx-1 btn-open-cloudinary btn-open-cloudinary-storage-' + credential.storageUid,
            button_caption: `<span
                class="t3js-icon icon icon-size-small icon-state-default">
                <svg class="icon-color">
                  <use
                    xlink:href="/typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/actions.svg#actions-cloud"
                  ></use>
                </svg>
              </span> Image or video from "${credential.storageName}"`, // todo translate me!
            // search: { expression: 'resource_type:image' }, // todo we could have video, how to filter _processed_file
          },
          {
            insertHandler: function (data) {
              NProgress.start();

              const me = this;
              const cloudinaryIds = data.assets.map((asset) => {
                return asset.public_id;
              });

              $.post(
                TYPO3.settings.ajaxUrls['cloudinary_add_files'],
                {
                  cloudinaryIds: cloudinaryIds,
                  storageUid: me.storageUid,
                },
                function (data) {
                  if (data.result === 'ok') {
                    data.files.map((fileUid) => {
                      MessageUtility.MessageUtility.send({
                        actionName: 'typo3:foreignRelation:insert',
                        objectGroup: me.objectGroup,
                        table: 'sys_file',
                        uid: fileUid,
                      });
                    });

                    NProgress.done();
                  } else {
                    // error!
                    const $confirm = Modal.confirm('ERROR', data.error, Severity.error, [
                      {
                        text: 'OK', // TYPO3.lang['file_upload.button.ok']
                        btnClass: 'btn-' + Severity.getCssClass(Severity.error),
                        name: 'ok',
                        active: true,
                      },
                    ]).on('confirm.button.ok', function () {
                      $confirm.modal('hide');
                    });
                  }

                  NProgress.done();
                }
              );
            },
          },
          '#' + $(element).attr('id')
        );
        mediaLibrary.storageUid = credential.storageUid;
        mediaLibrary.objectGroup = $(element).data('objectGroup');
      });

      // We update the "initialized" flag so that we don't have many buttons initialized
      $(element).attr('data-is-initialized', "1")
      console.log('Cloudinary button initialized for field id #' + $(element).attr('id'))
    })
  }

  // We trigger a rendering for the normal case
  initializeCloudinaryButtons ()
});
