<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Image;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Image\ImageInterface;
use Drupal\KernelTests\KernelTestBase;

// cspell:ignore imagecreatefrom

/**
 * Tests for the GD image toolkit.
 *
 * @coversDefaultClass \Drupal\system\Plugin\ImageToolkit\GDToolkit
 * @group Image
 * @requires extension gd
 */
class ToolkitGdTest extends KernelTestBase {

  /**
   * Colors that are used in testing.
   */
  protected const BLACK              = [0, 0, 0, 0];
  protected const RED                = [255, 0, 0, 0];
  protected const GREEN              = [0, 255, 0, 0];
  protected const BLUE               = [0, 0, 255, 0];
  protected const YELLOW             = [255, 255, 0, 0];
  protected const WHITE              = [255, 255, 255, 0];
  protected const TRANSPARENT        = [0, 0, 0, 127];
  protected const FUCHSIA            = [255, 0, 255, 0];
  protected const ROTATE_TRANSPARENT = [255, 255, 255, 127];

  /**
   * The image factory service.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected ImageFactory $imageFactory;

  /**
   * A directory where test image files can be saved to.
   *
   * @var string
   */
  protected string $directory;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);

    // Set the image factory service.
    $this->imageFactory = $this->container->get('image.factory');
    $this->assertEquals('gd', $this->imageFactory->getToolkitId(), 'The image factory is set to use the \'gd\' image toolkit.');

    // Prepare a directory for test file results.
    $this->directory = 'public://image_test';
    \Drupal::service('file_system')->prepareDirectory($this->directory, FileSystemInterface::CREATE_DIRECTORY);
  }

  /**
   * Assert two colors are equal by RGBA, net of full transparency.
   *
   * @param int[] $expected
   *   The expected RGBA array.
   * @param int[] $actual
   *   The actual RGBA array.
   * @param int $tolerance
   *   The acceptable difference between the colors.
   * @param string $message
   *   The assertion message.
   */
  protected function assertColorsAreEqual(array $expected, array $actual, int $tolerance, string $message = ''): void {
    // Fully transparent colors are equal, regardless of RGB.
    if ($actual[3] == 127 && $expected[3] == 127) {
      return;
    }
    $distance = pow(($actual[0] - $expected[0]), 2) + pow(($actual[1] - $expected[1]), 2) + pow(($actual[2] - $expected[2]), 2) + pow(($actual[3] - $expected[3]), 2);
    $this->assertLessThanOrEqual($tolerance, $distance, $message . " - Actual: {" . implode(',', $actual) . "}, Expected: {" . implode(',', $expected) . "}, Distance: " . $distance . ", Tolerance: " . $tolerance);
  }

  /**
   * Function for finding a pixel's RGBa values.
   */
  public function getPixelColor(ImageInterface $image, int $x, int $y): array {
    $toolkit = $image->getToolkit();
    $color_index = imagecolorat($toolkit->getImage(), $x, $y);

    $transparent_index = imagecolortransparent($toolkit->getImage());
    if ($color_index == $transparent_index) {
      return [0, 0, 0, 127];
    }

    return array_values(imagecolorsforindex($toolkit->getImage(), $color_index));
  }

  /**
   * Data provider for ::testManipulations().
   */
  public static function providerTestImageFiles(): array {
    // Typically the corner colors will be unchanged. These colors are in the
    // order of top-left, top-right, bottom-right, bottom-left.
    $default_corners = [static::RED, static::GREEN, static::BLUE, static::TRANSPARENT];

    // Setup a list of tests to perform on each type.
    $test_cases = [
      'resize' => [
        'operation' => 'resize',
        'arguments' => ['width' => 20, 'height' => 10],
        'width' => 20,
        'height' => 10,
        'corners' => $default_corners,
      ],
      'scale_x' => [
        'operation' => 'scale',
        'arguments' => ['width' => 20],
        'width' => 20,
        'height' => 10,
        'corners' => $default_corners,
      ],
      'scale_y' => [
        'operation' => 'scale',
        'arguments' => ['height' => 10],
        'width' => 20,
        'height' => 10,
        'corners' => $default_corners,
      ],
      'upscale_x' => [
        'operation' => 'scale',
        'arguments' => ['width' => 80, 'upscale' => TRUE],
        'width' => 80,
        'height' => 40,
        'corners' => $default_corners,
      ],
      'upscale_y' => [
        'operation' => 'scale',
        'arguments' => ['height' => 40, 'upscale' => TRUE],
        'width' => 80,
        'height' => 40,
        'corners' => $default_corners,
      ],
      'crop' => [
        'operation' => 'crop',
        'arguments' => ['x' => 12, 'y' => 4, 'width' => 16, 'height' => 12],
        'width' => 16,
        'height' => 12,
        'corners' => array_fill(0, 4, static::WHITE),
      ],
      'scale_and_crop' => [
        'operation' => 'scale_and_crop',
        'arguments' => ['width' => 10, 'height' => 8],
        'width' => 10,
        'height' => 8,
        'corners' => array_fill(0, 4, static::BLACK),
      ],
      'convert_jpg' => [
        'operation' => 'convert',
        'width' => 40,
        'height' => 20,
        'arguments' => ['extension' => 'jpeg'],
        'corners' => $default_corners,
      ],
      'convert_gif' => [
        'operation' => 'convert',
        'width' => 40,
        'height' => 20,
        'arguments' => ['extension' => 'gif'],
        'corners' => $default_corners,
      ],
      'convert_png' => [
        'operation' => 'convert',
        'width' => 40,
        'height' => 20,
        'arguments' => ['extension' => 'png'],
        'corners' => $default_corners,
      ],
      'convert_webp' => [
        'operation' => 'convert',
        'width' => 40,
        'height' => 20,
        'arguments' => ['extension' => 'webp'],
        'corners' => $default_corners,
      ],
    ];

    // Systems using non-bundled GD2 may miss imagerotate(). Test if available.
    if (function_exists('imagerotate')) {
      $test_cases += [
        'rotate_5' => [
          'operation' => 'rotate',
          // Fuchsia background.
          'arguments' => ['degrees' => 5, 'background' => '#FF00FF'],
          // @todo Re-enable dimensions' check once
          //   https://www.drupal.org/project/drupal/issues/2921123 is resolved.
          // 'width' => 41,
          // 'height' => 23,
          'corners' => array_fill(0, 4, static::FUCHSIA),
        ],
        'rotate_transparent_5' => [
          'operation' => 'rotate',
          'arguments' => ['degrees' => 5],
          // @todo Re-enable dimensions' check once
          //   https://www.drupal.org/project/drupal/issues/2921123 is resolved.
          // 'width' => 41,
          // 'height' => 23,
          'corners' => array_fill(0, 4, static::ROTATE_TRANSPARENT),
        ],
        'rotate_90' => [
          'operation' => 'rotate',
          // Fuchsia background.
          'arguments' => ['degrees' => 90, 'background' => '#FF00FF'],
          'width' => 20,
          'height' => 40,
          'corners' => [static::TRANSPARENT, static::RED, static::GREEN, static::BLUE],
        ],
        'rotate_transparent_90' => [
          'operation' => 'rotate',
          'arguments' => ['degrees' => 90],
          'width' => 20,
          'height' => 40,
          'corners' => [static::TRANSPARENT, static::RED, static::GREEN, static::BLUE],
        ],
      ];
    }

    // Systems using non-bundled GD2 may miss imagefilter(). Test if available.
    if (function_exists('imagefilter')) {
      $test_cases += [
        'desaturate' => [
          'operation' => 'desaturate',
          'arguments' => [],
          'height' => 20,
          'width' => 40,
          // Grayscale corners are a bit funky. Each of the corners are a shade of
          // gray. The values of these were determined simply by looking at the
          // final image to see what desaturated colors end up being.
          'corners' => [
            array_fill(0, 3, 76) + [3 => 0],
            array_fill(0, 3, 149) + [3 => 0],
            array_fill(0, 3, 29) + [3 => 0],
            array_fill(0, 3, 225) + [3 => 127],
          ],
        ],
      ];
    }

    $ret = [];
    foreach ([
      'image-test.png',
      'image-test.gif',
      'image-test-no-transparency.gif',
      'image-test.jpg',
      'img-test.webp',
    ] as $file_name) {
      foreach ($test_cases as $test_case => $values) {
        $operation = $values['operation'];
        $arguments = $values['arguments'];
        unset($values['operation'], $values['arguments']);
        $ret[] = [$file_name, $test_case, $operation, $arguments, $values];
      }
    }

    return $ret;
  }

  /**
   * Tests height, width and color for the corners for the final images.
   *
   * Since PHP can't visually check that our images have been manipulated
   * properly, build a list of expected color values for each of the corners and
   * the expected height and widths for the final images.
   *
   * @dataProvider providerTestImageFiles
   */
  public function testManipulations(string $file_name, string $test_case, string $operation, array $arguments, array $expected): void {
    // Load up a fresh image.
    $image = $this->imageFactory->get('core/tests/fixtures/files/' . $file_name);
    $toolkit = $image->getToolkit();
    $this->assertTrue($image->isValid());
    $image_original_type = $image->getToolkit()->getType();

    $this->assertTrue(imageistruecolor($toolkit->getImage()), "Image '$file_name' after load should be a truecolor image, but it is not.");

    // Perform our operation.
    $image->apply($operation, $arguments);

    // Flush Image object to disk storage.
    $file_path = $this->directory . '/' . $test_case . image_type_to_extension($image->getToolkit()->getType());
    $image->save($file_path);

    // Check that the both the GD object and the Image object have an accurate
    // record of the dimensions.
    if (isset($expected['height']) && isset($expected['width'])) {
      $this->assertSame($expected['height'], imagesy($toolkit->getImage()), "Image '$file_name' after '$test_case' should have a proper height.");
      $this->assertSame($expected['width'], imagesx($toolkit->getImage()), "Image '$file_name' after '$test_case' should have a proper width.");
      $this->assertSame($expected['height'], $image->getHeight(), "Image '$file_name' after '$test_case' should have a proper height.");
      $this->assertSame($expected['width'], $image->getWidth(), "Image '$file_name' after '$test_case' should have a proper width.");
    }

    // Now check each of the corners to ensure color correctness.
    foreach ($expected['corners'] as $key => $expected_color) {
      // The test gif that does not have transparency color set is a
      // special case.
      if ($file_name === 'image-test-no-transparency.gif') {
        if ($test_case == 'desaturate') {
          // For desaturating, keep the expected color from the test
          // data, but set alpha channel to fully opaque.
          $expected_color[3] = 0;
        }
        elseif ($expected_color === static::TRANSPARENT) {
          // Set expected pixel to yellow where the others have
          // transparent.
          $expected_color = static::YELLOW;
        }
      }

      // Get the location of the corner.
      [$x, $y] = match ($key) {
        0 => [0, 0],
        1 => [$image->getWidth() - 1, 0],
        2 => [$image->getWidth() - 1, $image->getHeight() - 1],
        3 => [0, $image->getHeight() - 1],
      };

      $actual_color = $this->getPixelColor($image, $x, $y);

      // If image cannot handle transparent colors, skip the pixel color test.
      if ($actual_color[3] === 0 && $expected_color[3] === 127) {
        continue;
      }

      // JPEG has small differences in color after processing.
      $tolerance = $image_original_type === IMAGETYPE_JPEG ? 3 : 0;

      $this->assertColorsAreEqual($expected_color, $actual_color, $tolerance, "Image '$file_name' object after '$test_case' action has the correct color placement at corner '$key'");
    }

    // Check that saved image reloads without raising PHP errors.
    $image_reloaded = $this->imageFactory->get($file_path);
    $this->assertInstanceOf(\GDImage::class, $image_reloaded->getToolkit()->getImage());
  }

  /**
   * @covers ::getSupportedExtensions
   * @covers ::extensionToImageType
   */
  public function testSupportedExtensions(): void {
    // Test the list of supported extensions.
    $expected_extensions = ['png', 'gif', 'jpeg', 'jpg', 'jpe', 'webp'];
    $this->assertEqualsCanonicalizing($expected_extensions, $this->imageFactory->getSupportedExtensions());

    // Test that the supported extensions map to correct internal GD image
    // types.
    $expected_image_types = [
      'png' => IMAGETYPE_PNG,
      'gif' => IMAGETYPE_GIF,
      'jpeg' => IMAGETYPE_JPEG,
      'jpg' => IMAGETYPE_JPEG,
      'jpe' => IMAGETYPE_JPEG,
      'webp' => IMAGETYPE_WEBP,
    ];
    $image = $this->imageFactory->get();
    foreach ($expected_image_types as $extension => $expected_image_type) {
      $this->assertSame($expected_image_type, $image->getToolkit()->extensionToImageType($extension));
    }
  }

  /**
   * Data provider for ::testCreateImageFromScratch().
   */
  public static function providerSupportedImageTypes(): array {
    return [
      [IMAGETYPE_PNG],
      [IMAGETYPE_GIF],
      [IMAGETYPE_JPEG],
      [IMAGETYPE_WEBP],
    ];
  }

  /**
   * Tests that GD functions for the image type are available.
   *
   * @dataProvider providerSupportedImageTypes
   */
  public function testGdFunctionsExist(int $type): void {
    $extension = image_type_to_extension($type, FALSE);
    $this->assertTrue(function_exists("imagecreatefrom$extension"), "imagecreatefrom$extension should exist.");
    $this->assertTrue(function_exists("image$extension"), "image$extension should exist.");
  }

  /**
   * Tests creation of image from scratch, and saving to storage.
   *
   * @dataProvider providerSupportedImageTypes
   */
  public function testCreateImageFromScratch(int $type): void {
    // Build an image from scratch.
    $image = $this->imageFactory->get();
    $image->createNew(50, 20, image_type_to_extension($type, FALSE), '#ffff00');
    $file = 'from_null' . image_type_to_extension($type);
    $file_path = $this->directory . '/' . $file;
    $this->assertSame(50, $image->getWidth());
    $this->assertSame(20, $image->getHeight());
    $this->assertSame(image_type_to_mime_type($type), $image->getMimeType());
    $this->assertTrue($image->save($file_path), "Image '$file' should have been saved successfully, but it has not.");

    // Reload and check saved image.
    $image_reloaded = $this->imageFactory->get($file_path);
    $this->assertTrue($image_reloaded->isValid());
    $this->assertSame(50, $image_reloaded->getWidth());
    $this->assertSame(20, $image_reloaded->getHeight());
    $this->assertSame(image_type_to_mime_type($type), $image_reloaded->getMimeType());
    if ($image_reloaded->getToolkit()->getType() == IMAGETYPE_GIF) {
      $this->assertSame('#ffff00', $image_reloaded->getToolkit()->getTransparentColor(), "Image '$file' after reload should have color channel set to #ffff00, but it has not.");
    }
    else {
      $this->assertNull($image_reloaded->getToolkit()->getTransparentColor(), "Image '$file' after reload should have no color channel set, but it has.");
    }
  }

  /**
   * Tests failures of the 'create_new' operation.
   */
  public function testCreateNewFailures(): void {
    $image = $this->imageFactory->get();
    $image->createNew(-50, 20);
    $this->assertFalse($image->isValid(), 'CreateNew with negative width fails.');
    $image->createNew(50, 20, 'foo');
    $this->assertFalse($image->isValid(), 'CreateNew with invalid extension fails.');
    $image->createNew(50, 20, 'gif', '#foo');
    $this->assertFalse($image->isValid(), 'CreateNew with invalid color hex string fails.');
    $image->createNew(50, 20, 'gif', '#ff0000');
    $this->assertTrue($image->isValid(), 'CreateNew with valid arguments validates the Image.');
  }

  /**
   * Tests for GIF images with transparency.
   */
  public function testGifTransparentImages(): void {
    // Test loading an indexed GIF image with transparent color set.
    // Color at top-right pixel should be fully transparent.
    $file = 'image-test-transparent-indexed.gif';
    $image = $this->imageFactory->get('core/tests/fixtures/files/' . $file);
    $gd_image = $image->getToolkit()->getImage();
    $color_index = imagecolorat($gd_image, $image->getWidth() - 1, 0);
    $color = array_values(imagecolorsforindex($gd_image, $color_index));
    $this->assertEquals(static::ROTATE_TRANSPARENT, $color, "Image {$file} after load has full transparent color at corner 1.");

    // Test deliberately creating a GIF image with no transparent color set.
    // Color at top-right pixel should be fully transparent while in memory,
    // fully opaque after flushing image to file.
    $file = 'image-test-no-transparent-color-set.gif';
    $file_path = $this->directory . '/' . $file;
    // Create image.
    $image = $this->imageFactory->get();
    $image->createNew(50, 20, 'gif', NULL);
    $gd_image = $image->getToolkit()->getImage();
    $color_index = imagecolorat($gd_image, $image->getWidth() - 1, 0);
    $color = array_values(imagecolorsforindex($gd_image, $color_index));
    $this->assertEquals(static::ROTATE_TRANSPARENT, $color, "New GIF image with no transparent color set after creation has full transparent color at corner 1.");
    // Save image.
    $this->assertTrue($image->save($file_path), "New GIF image {$file} was saved.");
    // Reload image.
    $image_reloaded = $this->imageFactory->get($file_path);
    $gd_image = $image_reloaded->getToolkit()->getImage();
    $color_index = imagecolorat($gd_image, $image_reloaded->getWidth() - 1, 0);
    $color = array_values(imagecolorsforindex($gd_image, $color_index));
    // Check explicitly for alpha == 0 as the rest of the color has been
    // compressed and may have slight difference from full white.
    $this->assertEquals(0, $color[3], "New GIF image {$file} after reload has no transparent color at corner 1.");

    // Test loading an image whose transparent color index is out of range.
    // This image was generated by taking an initial image with a palette size
    // of 6 colors, and setting the transparent color index to 6 (one higher
    // than the largest allowed index), as follows:
    // @code
    // $image = imagecreatefromgif('core/tests/fixtures/files/image-test.gif');
    // imagecolortransparent($image, 6);
    // imagegif($image, 'core/tests/fixtures/files/image-test-transparent-out-of-range.gif');
    // @endcode
    // This allows us to test that an image with an out-of-range color index
    // can be loaded correctly.
    $file = 'image-test-transparent-out-of-range.gif';
    $image = $this->imageFactory->get('core/tests/fixtures/files/' . $file);
    $this->assertTrue($image->isValid(), "Image '$file' after load should be valid, but it is not.");
    $this->assertTrue(imageistruecolor($image->getToolkit()->getImage()), "Image '$file' after load should be a truecolor image, but it is not.");
  }

  /**
   * Tests calling a missing image operation plugin.
   */
  public function testMissingOperation(): void {
    // Load up a fresh image.
    $image = $this->imageFactory->get('core/tests/fixtures/files/image-test.png');
    $this->assertTrue($image->isValid(), "Image 'image-test.png' after load should be valid, but it is not.");

    // Try perform a missing toolkit operation.
    $this->assertFalse($image->apply('missing_op', []), 'Calling a missing image toolkit operation plugin should fail, but it did not.');
  }

  /**
   * @covers ::getRequirements
   */
  public function testGetRequirements(): void {
    $this->assertEquals([
      'version' => [
        'title' => t('GD library'),
        'value' => gd_info()['GD Version'],
        'description' => t("Supported image file formats: %formats.", [
          '%formats' => implode(', ', ['GIF', 'JPEG', 'PNG', 'WEBP']),
        ]),
      ],
    ], $this->imageFactory->get()->getToolkit()->getRequirements());
  }

  /**
   * Tests deprecated setResource() and getResource().
   *
   * @group legacy
   */
  public function testResourceDeprecation(): void {
    $toolkit = $this->imageFactory->get()->getToolkit();
    $image = imagecreate(10, 10);
    $this->expectDeprecation('Drupal\system\Plugin\ImageToolkit\GDToolkit::setResource() is deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use \Drupal\system\Plugin\ImageToolkit\GDToolkit::setImage() instead. See https://www.drupal.org/node/3265963');
    $toolkit->setResource($image);
    $this->expectDeprecation('Checking the \Drupal\system\Plugin\ImageToolkit\GDToolkit::resource property is deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use \Drupal\system\Plugin\ImageToolkit\GDToolkit::image instead. See https://www.drupal.org/node/3265963');
    $this->assertTrue(isset($toolkit->resource));
    $this->expectDeprecation('Drupal\system\Plugin\ImageToolkit\GDToolkit::getResource() is deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use \Drupal\system\Plugin\ImageToolkit\GDToolkit::getImage() instead. See https://www.drupal.org/node/3265963');
    $this->assertSame($image, $toolkit->getResource());
    $this->expectDeprecation('Accessing the \Drupal\system\Plugin\ImageToolkit\GDToolkit::resource property is deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use \Drupal\system\Plugin\ImageToolkit\GDToolkit::image instead. See https://www.drupal.org/node/3265963');
    $this->assertSame($image, $toolkit->resource);
    $this->expectDeprecation('Setting the \Drupal\system\Plugin\ImageToolkit\GDToolkit::resource property is deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use \Drupal\system\Plugin\ImageToolkit\GDToolkit::image instead. See https://www.drupal.org/node/3265963');
    $toolkit->resource = NULL;
    $this->assertNull($toolkit->getImage());
    $this->expectDeprecation('Unsetting the \Drupal\system\Plugin\ImageToolkit\GDToolkit::resource property is deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use \Drupal\system\Plugin\ImageToolkit\GDToolkit::image instead. See https://www.drupal.org/node/3265963');
    $toolkit->setImage($image);
    unset($toolkit->resource);
    $this->assertNull($toolkit->getImage());
  }

}
