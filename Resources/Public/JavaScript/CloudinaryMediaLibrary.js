/*jshint -W033, -W003, -W116, -W069 */
/*globals define:false, cloudinary:false, cloudinaryCredentials:false, TYPO3:false */
define([
  'jquery',
  'lit',
  'nprogress',
  'TYPO3/CMS/Backend/Utility/MessageUtility',
  'TYPO3/CMS/Backend/Modal',
  'TYPO3/CMS/Backend/Severity',
], function ($, lit, NProgress, MessageUtility, Modal, Severity) {
  const { render, html } = lit;

  function getCloudinaryCredentials() {
    return Array.isArray(cloudinaryCredentials) ? cloudinaryCredentials : [];
  }

  // Generate the login option
  $('.btn-cloudinary-media-library[data-is-initialized="0"]').map((index, element) => {
    getCloudinaryCredentials().map((credential) => {
      // Render the "select image or video" button
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
          // showHandler: function () {},
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
  });
});
