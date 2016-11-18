<?php

/*
* File: SimpleImage.php
* Author: Simon Jarvis
* Copyright: 2006 Simon Jarvis
* Date: 08/11/06
* Link: http://www.white-hat-web-design.co.uk/articles/php-image-resizing.php
*
* This program is free software; you can redistribute it and/or
* modify it under the terms of the GNU General Public License
* as published by the Free Software Foundation; either version 2
* of the License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details:
* http://www.gnu.org/licenses/gpl.html
*
* Additional functions added by ROYG on 2014-11-13:
*   load_data($image_data)
*   resizeToWidthHeight ()
*   resizeDownToWidthHeight ()
*/

class SimpleImage {
   var $image;
   var $image_type;
   function load_data($image_data) {
      $image_info = getimagesizefromstring($image_data);
      $this->image_type = $image_info[2];
      $this->image = imagecreatefromstring($image_data);
   }
   function load($filename) {
      $image_info = getimagesize($filename);
      $this->image_type = $image_info[2];
      if( $this->image_type == IMAGETYPE_JPEG ) {
         $this->image = imagecreatefromjpeg($filename);
      } elseif( $this->image_type == IMAGETYPE_GIF ) {
         $this->image = imagecreatefromgif($filename);
      } elseif( $this->image_type == IMAGETYPE_PNG ) {
         $this->image = imagecreatefrompng($filename);
      }
   }
   function save($filename, $image_type=IMAGETYPE_JPEG, $compression=75, $permissions=null) {
      if( $image_type == IMAGETYPE_JPEG ) {
         imagejpeg($this->image,$filename,$compression);
      } elseif( $image_type == IMAGETYPE_GIF ) {
         imagegif($this->image,$filename);
      } elseif( $image_type == IMAGETYPE_PNG ) {
         imagepng($this->image,$filename);
      }
      if( $permissions != null) {
         chmod($filename,$permissions);
      }
   }
   function output($image_type=IMAGETYPE_JPEG) {
      if( $image_type == IMAGETYPE_JPEG ) {
         imagejpeg($this->image);
      } elseif( $image_type == IMAGETYPE_GIF ) {
         imagegif($this->image);
      } elseif( $image_type == IMAGETYPE_PNG ) {
         imagepng($this->image);
      }
   }
   function getWidth() {
      return imagesx($this->image);
   }
   function getHeight() {
      return imagesy($this->image);
   }
   function resizeToWidth($width) {
      $ratio = $width / $this->getWidth();
      $height = $this->getHeight() * $ratio;
      $this->resize($width,$height);
   }
   function resizeToHeight($height) {
      $ratio = $height / $this->getHeight();
      $width = $this->getWidth() * $ratio;
      $this->resize($width,$height);
   }
   function resizeToWidthHeight($width_height) {
      if ($this->getWidth() > $this->getHeight()) $this->resizeToWidth($width_height);
      else $this->resizeToHeight($width_height);
   }
   function resizeDownToWidthHeight($width_height) {
      if ($this->getWidth() > $this->getHeight() && $this->getWidth() > $width_height) $this->resizeToWidth($width_height);
      elseif ($this->getHeight() > $width_height) $this->resizeToHeight($width_height);
   }
   function scale($scale) {
      $width = $this->getWidth() * $scale/100;
      $height = $this->getHeight() * $scale/100;
      $this->resize($width,$height);
   }
   function resize($width,$height) {
      $new_image = imagecreatetruecolor($width, $height);
      imagecopyresampled($new_image, $this->image, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
      $this->image = $new_image;
   }
}

// This function returns an <img> HTML tag with the the path to the image file in the product images folder.
// If the image does not exist, it is retrieved from the database, resized, and saved.
// If the function was not able to create an image file, it returns a bogus empty img tag with the reason
// for failure in the alt attribute.
function get_image_tag_by_id ($image_id)
{
    global $connection;
    
    // First check to see if the image exists as a file
    $file = PRODUCT_IMAGE_PATH.'img'.PRODUCT_IMAGE_SIZE.'-'.$image_id.'.png';
    $image_error = '';
    
    if (!file_exists(FILE_PATH.$file)) 
    {
        // If the image does not exist, then get it from the database
        $query = '
          SELECT *
          FROM '.TABLE_PRODUCT_IMAGES.'
          WHERE image_id = "'.$image_id.'"';
        $result = @mysql_query($query, $connection);
        if (!$result) 
        {
            debug_print ("ERROR: 785922 ", array ($query, mysql_error()), basename(__FILE__).' LINE '.__LINE__);
            $image_error = 'No image ID '.$image_id . ' found in ' . TABLE_PRODUCT_IMAGES;
        }

        if (!$image_error) 
        {
            $row = mysql_fetch_array($result);

            $image = new SimpleImage();
            $image->load_data($row['image_content']);
            // If we don't have a width or height for this image in the database, then save it now.
            if ($row['width'] == 0 || $row['height'] == 0)
            {
                $image_info = getimagesizefromstring($row['image_content']);
                $original_width = $image_info[0];
                $original_height = $image_info[1];
                $query = '
                  UPDATE '.TABLE_PRODUCT_IMAGES.'
                  SET width = "'.$original_width.'",
                    height = "'.$original_height.'"
                  WHERE image_id = "'.mysql_real_escape_string($_GET['image_id']).'"';
                $result = @mysql_query($query, $connection);     // We don't particularly care if the update fails
            }
            $image->resizeDownToWidthHeight(PRODUCT_IMAGE_SIZE);
            $image->save(FILE_PATH.$file, IMAGETYPE_PNG);
        }
    }
    
    if (!$image_error && !file_exists($file))
    {
        $image_error = 'Could not create file ' . $file . 'for image ID ' . $image_id;
    }

    return '<img src="'.(file_exists(FILE_PATH.$file) ? $file : '#').'" class="product_image" alt="'.($image_error ? $image_error : '').'" title="Hold down mouse button to enlarge">';
}
