import $ from 'jquery';
import NProgress from 'nprogress';
import {MessageUtility} from '@typo3/backend/utility/message-utility.js';
import Modal from '@typo3/backend/modal.js';
import Severity from '@typo3/backend/severity.js';

const cloudinaryMediaLibraryUrl = 'https://media-library.cloudinary.com/global/all.js';
let cloudinaryMediaLibraryPromise = null;

const loadCloudinaryMediaLibrary = () => {
  if (window.cloudinary?.openMediaLibrary) {
    return Promise.resolve();
  }

  if (cloudinaryMediaLibraryPromise === null) {
    cloudinaryMediaLibraryPromise = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = cloudinaryMediaLibraryUrl;
      script.async = true;
      script.addEventListener('load', () => resolve());
      script.addEventListener('error', () => reject(new Error('Could not load Cloudinary media library.')));
      document.head.appendChild(script);
    });
  }

  return cloudinaryMediaLibraryPromise;
};

loadCloudinaryMediaLibrary();

let cloudinaryButtons = Array.from(document.getElementsByClassName('btn-cloudinary-media-library'));
setCloudinaryButtonEvent(cloudinaryButtons);

$('.t3js-create-new-button').click(function(e) {
  setTimedOutedCloudinaryButtonEvent();
})

$('.form-irre-header-button').click(function(e) {
  setTimedOutedCloudinaryButtonEvent();
})

function setTimedOutedCloudinaryButtonEvent(){
  setTimeout(() =>
    {setCloudinaryButtonEvent(Array.from(document.getElementsByClassName('btn-cloudinary-media-library')))},
    1000
  );
}

function setCloudinaryButtonEvent(cloudinaryButtons) {
  cloudinaryButtons.map((cloudinaryButton) => {
    cloudinaryButton.addEventListener("click", function(event){
      event.preventDefault();
      let buttonClasses = $(this).attr('class');
      let buttonInnerHtml = $(this).prop("innerHTML");
      let objectGroup = $(this).data('objectGroup');
      let elementId = $(this).attr('id');
      openMediaLibrary(JSON.parse(cloudinaryButton.dataset.cloudinaryCredentials), objectGroup, elementId, buttonClasses, buttonInnerHtml);
    });
    cloudinaryButton.removeAttribute('disabled');
  });
}

function openMediaLibrary(credential, objectGroup, elementId, buttonClasses, buttonInnerHtml) {
  loadCloudinaryMediaLibrary().then(() => {
    // Render the cloudinary button
    const mediaLibrary = window.cloudinary.openMediaLibrary(
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
                  MessageUtility.send({
                    actionName: 'typo3:foreignRelation:insert',
                    objectGroup: me.objectGroup,
                    table: 'sys_file',
                    uid: fileUid,
                  });
                });

                NProgress.done();
              } else {
                // error!
                const confirm = Modal.confirm('ERROR', data.error, Severity.error, [
                  {
                    text: 'OK', // TYPO3.lang['file_upload.button.ok']
                    btnClass: 'btn-' + Severity.getCssClass(Severity.error),
                    name: 'ok',
                    active: true,
                  },
                ]);
                confirm.addEventListener('confirm.button.ok', () => confirm.hideModal());
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
  });
}
