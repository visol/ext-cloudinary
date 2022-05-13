/*jshint -W033, -W003, -W116 */
/*globals define:false, cloudinary:false, cloudinaryCredentials:false */
define(['jquery', 'lit'], function ($, lit) {
  const { render, html } = lit;

  function getCloudinaryCredentials() {
    return Array.isArray(cloudinaryCredentials) ? cloudinaryCredentials : [];
  }

  function getItems() {
    const items = JSON.parse(document.getElementById('field-media-library').value);
    return Array.isArray(items) ? items : [];
  }

  function setItems(items) {
    document.getElementById('field-media-library').value = JSON.stringify(items, null, 2);
  }

  function removeItem(publicId) {
    const items = getItems();
    return items.filter((item) => {
      return item.public_id !== publicId;
    });
  }

  function getThumbnailUrl(url) {
    return url.replace('upload/', 'upload/w_100/');
  }

  function move(array, index, delta) {
    //ref: https://gist.github.com/albertein/4496103
    const newIndex = index + delta;
    if (newIndex < 0 || newIndex === array.length) return; //Already at the top or bottom.
    const indexes = [index, newIndex].sort((a, b) => a - b); //Sort the indixes (fixed)
    array.splice(indexes[0], 2, array[indexes[1]], array[indexes[0]]); //Replace from lowest index, two elements, reverting the order
  }

  function moveUp(array, element) {
    move(array, element, -1);
  }

  function moveDown(array, element) {
    move(array, element, 1);
  }

  // Add listener
  $('#container-cloudinary-images')
    .on('click', '.btn-remove-cloudinary-resource', function () {
      const items = removeItem(this.dataset.publicId);
      setItems(items);
      renderList();
    })
    .on('click', '.btn-up-cloudinary-resource', function () {
      const items = getItems();
      const index = items.findIndex((resource) => resource.public_id === this.dataset.publicId);
      moveUp(items, index);
      setItems(items);
      renderList();
    })
    .on('click', '.btn-down-cloudinary-resource', function () {
      const items = getItems();
      const index = items.findIndex((resource) => resource.public_id === this.dataset.publicId);
      moveDown(items, index);
      setItems(items);
      renderList();
    });

  function renderList() {
    const resources = getItems();
    render(
      html` <ul class="list-group ">
        ${resources.map((resource, index) => {
          const thumbnailUrl = getThumbnailUrl(resource.secure_url);
          return html` <li style="padding-top: 20px" class="list-group-item">
            <a href="${resource.secure_url}" target="_blank">
              <img src="${thumbnailUrl}" alt="" style="width: 100px"
            /></a>
            <button
              type="button"
              class="btn btn-default btn-sm btn-remove-cloudinary-resource"
              data-public-id="${resource.public_id}"
            >
              <span class="t3js-icon icon icon-size-small icon-state-default">
                <svg class="icon-color">
                  <use
                    xlink:href="/typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/actions.svg#actions-delete"
                  ></use>
                </svg>
              </span>
            </button>
            <button
              type="button"
              class="btn btn-default btn-sm btn-up-cloudinary-resource"
              data-public-id="${resource.public_id}"
            >
              <span
                class="t3js-icon icon icon-size-small icon-state-default"
                style="visibility: ${index > 0 ? '' : 'hidden'}"
              >
                <svg class="icon-color">
                  <use
                    xlink:href="/typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/actions.svg#actions-caret-up"
                  ></use>
                </svg>
              </span>
            </button>

            <button
              type="button"
              class="btn btn-default btn-sm btn-down-cloudinary-resource"
              data-public-id="${resource.public_id}"
            >
              <span
                class="t3js-icon icon icon-size-small icon-state-default"
                style="visibility: ${index + 1 < resources.length ? '' : 'hidden'}"
              >
                <svg class="icon-color">
                  <use
                    xlink:href="/typo3/sysext/core/Resources/Public/Icons/T3Icons/sprites/actions.svg#actions-caret-down"
                  ></use>
                </svg>
              </span>
            </button>
          </li>`;
        })}
      </ul>`,
      document.getElementById('list-cloudinary-resources')
    );
  }

  // Generate the login option
  getCloudinaryCredentials().map((credential) => {
    // Render the "select image or video" button
    cloudinary.createMediaLibrary(
      {
        cloud_name: credential.cloudName,
        api_key: credential.apiKey,
        username: credential.username,
        timestamp: credential.timestamp,
        signature: credential.signature,
        button_class: 'btn btn-default btn-sm open-btn mx-2',
        button_caption: `Image or video from "${credential.name}"`,
        search: { expression: 'resource_type:video' },
      },
      {
        insertHandler: function (data) {
          setItems(data.assets);
          renderList();
        },
      },
      '#btn-cloudinary-media-library'
    );
  });

  // Finally render the list
  renderList();
});
