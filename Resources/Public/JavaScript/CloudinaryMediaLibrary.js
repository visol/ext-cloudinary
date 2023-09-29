/*jshint -W033, -W003, -W116, -W069 */
/*globals define:false, cloudinary:false, cloudinaryCredentials:false, TYPO3:false */
define([
  'jquery',
  'nprogress',
  'TYPO3/CMS/Backend/Utility/MessageUtility',
  'TYPO3/CMS/Backend/Modal',
  'TYPO3/CMS/Backend/Severity',
  '//media-library.cloudinary.com/global/all.js',
], function ($, NProgress, MessageUtility, Modal, Severity) {

  let cloudinaryButtons = Array.from(document.getElementsByClassName('btn-cloudinary-media-library'));

  cloudinaryButtons.map((cloudinaryButton) => {
    cloudinaryButton.addEventListener("click", function(event){
      event.preventDefault();
      let buttonClasses = $(this).attr('class');
      let buttonInnerHtml = $(this).prop("innerHTML");
      let objectGroup = $(this).data('objectGroup');
      let elementId = $(this).attr('id');
      openMediaLibrary(JSON.parse(cloudinaryButton.dataset.cloudinaryCredentials), objectGroup, elementId, buttonClasses, buttonInnerHtml);
    });
  });

  function openMediaLibrary(credential, objectGroup, elementId, buttonClasses, buttonInnerHtml) {
    // Render the cloudinary button
    const mediaLibrary = cloudinary.openMediaLibrary(
      {
        cloud_name: credential.cloudName,
        api_key: credential.apiKey,
        username: credential.username,
        timestamp: credential.timestamp,
        signature: credential.signature,
        button_class: buttonClasses,
        button_caption: buttonInnerHtml,
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
      '#' + elementId
    );
    mediaLibrary.storageUid = credential.storageUid;
    mediaLibrary.objectGroup = objectGroup;
  }
});
