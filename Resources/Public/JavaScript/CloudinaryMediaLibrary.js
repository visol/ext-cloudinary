window.ml = cloudinary.createMediaLibrary(
  {
    cloud_name: 'fabidule', // 'jungfrau-ch-test',
    api_key: '335525476748139', // '353283611841452',
    username: 'fabien.udriot@visol.ch', // webmaster@jungfrau.ch
    //use_saml: true,
    button_class: 'btn btn-default btn-sm open-btn',
    button_caption: 'Select Image or Video',
  },
  {
    insertHandler: function (data) {
      document.getElementById('field-media-library').value = JSON.stringify(data.assets, null, 2);
      renderCloudinaryResourcesList(data.assets);
    },
  },
  '#btn-cloudinary-media-library'
);

const cloudinaryResources = JSON.parse(document.getElementById('field-media-library').value);
renderCloudinaryResourcesList(cloudinaryResources);

function renderCloudinaryResourcesList(items) {
  document.getElementById('list-cloudinary-resources').innerHTML = '';
  if (Array.isArray(items)) {
    const list = document.createElement('ul');
    items.map((resource) => {
      //console.log(resource);
      const image = document.createElement('img');
      image.src = resource.secure_url;
      image.width = 100;

      const elem = document.createElement('li');
      elem.style.paddingTop = '20px';
      elem.appendChild(image);
      list.appendChild(elem);
    });
    document.getElementById('list-cloudinary-resources').append(list);
  }
}
