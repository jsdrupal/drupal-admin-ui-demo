<?php

namespace Drupal\Tests\jsonapi\Kernel\Field;

use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\jsonapi\Field\FileDownloadUrl
 * @group jsonapi
 * @group legacy
 *
 * @internal
 */
class FileDownloadUrlTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'jsonapi',
    'file',
    'serialization',
    'text',
    'user',
  ];

  /**
   * The test file.
   *
   * @var \Drupal\file\Entity\File
   */
  protected $file;

  /**
   * The test filename.
   *
   * @var string
   */
  protected $filename = 'druplicon.txt';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    // Create a new file entity.
    $this->file = File::create([
      'filename' => $this->filename,
      'uri' => sprintf('public://%s', $this->filename),
      'filemime' => 'text/plain',
      'status' => FILE_STATUS_PERMANENT,
    ]);

    $this->file->save();
  }

  /**
   * Test the URL computed field.
   */
  public function testUrlField() {
    $url_field = $this->file->get('url');
    // Test all the different ways to access a field item.
    $values = [
      $url_field->value,
      $url_field->getValue()[0]['value'],
      $url_field->get(0)->toArray()['value'],
      $url_field->first()->getValue()['value'],
    ];
    array_walk($values, function ($value) {
      $this->assertContains('simpletest', $value);
      $this->assertContains($this->filename, $value);
    });
    $violationList = $this->file->validate();
    $this->assertEquals(0, $violationList->count());
  }

}
