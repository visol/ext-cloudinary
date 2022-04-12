<?php

namespace Visol\Cloudinary\Userfuncs;

class Tca
{
    public function mediaLibraryField($PA, $fObj): string
    {
        $formField = '<div>';
        $formField .= '<script src="https://media-library.cloudinary.com/global/all.js"></script>';
        $formField .= '<div id="open-btn">asdfasdf</div>';
        $formField .= "<script>
			window.ml = cloudinary.createMediaLibrary(
				{
					cloud_name: 'jungfrau-ch-test',
					api_key: '353283611841452',
					username: 'webmaster@jungfrau.ch',
					button_class: 'myBtn',
					button_caption: 'Select Image or Video'
				}, {
					insertHandler: function (data) {
						data.assets.forEach(asset => {
							console.log('Inserted asset:', JSON.stringify(asset, null, 2))
						})
					}
				},
				'#open-btn'
			)
		</script>";
        $formField .= '<input type="text" name="' . $PA['itemFormElName'] . '"';
        $formField .= ' value="' . htmlspecialchars($PA['itemFormElValue']) . '"';
        $formField .= ' onchange="' . htmlspecialchars(implode('', $PA['fieldChangeFunc'])) . '"';
        $formField .= $PA['onFocus'];
        $formField .= ' /></div>';
        return $formField;
    }
}
