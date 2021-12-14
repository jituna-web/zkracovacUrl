<?php
define('VERSION', '0.1.1');

$timestart = microtime(TRUE);
$GLOBALS['status'] = array();

$unzipper = new Unzipper;
if (isset($_POST['dounzip'])) {
  // Zkontroluje, zda byl k rozbalení vybrán archiv.
  $archive = isset($_POST['zipfile']) ? strip_tags($_POST['zipfile']) : '';
  $destination = isset($_POST['extpath']) ? strip_tags($_POST['extpath']) : '';
  $unzipper->prepareExtraction($archive, $destination);
}

if (isset($_POST['dozip'])) {
  $zippath = !empty($_POST['zippath']) ? strip_tags($_POST['zippath']) : '.';
  // Výsledný zipfile např. zip--2016-07-23--11-55.zip.
  $zipfile = 'zipper-' . date("Y-m-d--H-i") . '.zip';
  Zipper::zipDir($zippath, $zipfile);
}

$timeend = microtime(TRUE);
$time = round($timeend - $timestart, 4);

/**
 * Třída Unzipper
 */
class Unzipper {
  public $localdir = '.';
  public $zipfiles = array();

  public function __construct() {
    // Projděte si adresář a vyberte soubory .zip, .rar a .gz.
    if ($dh = opendir($this->localdir)) {
      while (($file = readdir($dh)) !== FALSE) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'zip'
          || pathinfo($file, PATHINFO_EXTENSION) === 'gz'
          || pathinfo($file, PATHINFO_EXTENSION) === 'rar'
        ) {
          $this->zipfiles[] = $file;
        }
      }
      closedir($dh);

      if (!empty($this->zipfiles)) {
        $GLOBALS['status'] = array('info' => '.zip or .gz or .rar nalezené soubory, připravené k extrakci');
      }
      else {
        $GLOBALS['status'] = array('info' => 'Žádné .zip nebo .gz nebo rar soubory nebyli nalezeny. K dispozici je tedy pouze funkce zipování.');
      }
    }
  }

  public function prepareExtraction($archive, $destination = '') {
    // určení cesty
    if (empty($destination)) {
      $extpath = $this->localdir;
    }
    else {
      $extpath = $this->localdir . '/' . $destination;
      // Úkol: přesunout do funkce extrakce.
      if (!is_dir($extpath)) {
        mkdir($extpath);
      }
    }
    // Extrahovat lze pouze místní existující archivy.
    if (in_array($archive, $this->zipfiles)) {
      self::extract($archive, $extpath);
    }
  }
  public static function extract($archive, $destination) {
    $ext = pathinfo($archive, PATHINFO_EXTENSION);
    switch ($ext) {
      case 'zip':
        self::extractZipArchive($archive, $destination);
        break;
      case 'gz':
        self::extractGzipFile($archive, $destination);
        break;
      case 'rar':
        self::extractRarArchive($archive, $destination);
        break;
    }

  }

  public static function extractZipArchive($archive, $destination) {
    // Zkontroluje, zda webový server podporuje rozbalení.
    if (!class_exists('ZipArchive')) {
      $GLOBALS['status'] = array('error' => 'Chyba: Vaše verze PHP nepodporuje funkci rozbalení.');
      return;
    }

    $zip = new ZipArchive;

    // Zkontroluje, zda je archiv čitelný.
    if ($zip->open($archive) === TRUE) {
      // Zkontroluje, zda lze do cíle zapisovat
      if (is_writeable($destination . '/')) {
        $zip->extractTo($destination);
        $zip->close();
        $GLOBALS['status'] = array('success' => 'Soubory byly úspěšně rozbaleny');
      }
      else {
        $GLOBALS['status'] = array('error' => 'Chyba: Do adresáře nelze zapisovat webovým serverem.');
      }
    }
    else {
      $GLOBALS['status'] = array('error' => 'Chyba: Archiv .zip nelze přečíst.');
    }
  }

  public static function extractGzipFile($archive, $destination) {
    // Zkontroluje, zda je povolen zlib
    if (!function_exists('gzopen')) {
      $GLOBALS['status'] = array('error' => 'Chyba: Vaše PHP nemá povolenou podporu.');
      return;
    }

    $filename = pathinfo($archive, PATHINFO_FILENAME);
    $gzipped = gzopen($archive, "rb");
    $file = fopen($destination . '/' . $filename, "w");

    while ($string = gzread($gzipped, 4096)) {
      fwrite($file, $string, strlen($string));
    }
    gzclose($gzipped);
    fclose($file);

    // Zkontroluje, zda byl soubor extrahován.
    if (file_exists($destination . '/' . $filename)) {
      $GLOBALS['status'] = array('success' => 'Soubor byl úspěšně rozbalen.');

      // Pokud jsme měli soubor tar.gz, rozbalme tento soubor tar.
      if (pathinfo($destination . '/' . $filename, PATHINFO_EXTENSION) == 'tar') {
        $phar = new PharData($destination . '/' . $filename);
        if ($phar->extractTo($destination)) {
          $GLOBALS['status'] = array('success' => 'Úspěšně extrahován archiv tar.gz.');
          // Delete .tar.
          unlink($destination . '/' . $filename);
        }
      }
    }
    else {
      $GLOBALS['status'] = array('error' => 'Při rozbalování souboru došlo k chybě.');
    }

  }

  public static function extractRarArchive($archive, $destination) {
    // Zkontroluje, zda webový server podporuje rozbalení.
    if (!class_exists('RarArchive')) {
      $GLOBALS['status'] = array('error' => 'Error: Your PHP version does not support .rar archive functionality. <a class="info" href="http://php.net/manual/en/rar.installation.php" target="_blank">How to install RarArchive</a>');
      return;
    }
    // Zkontroluje, zda je archiv čitelný.
    if ($rar = RarArchive::open($archive)) {
      // Zkontroluje, zda lze do cíle zapisovat
      if (is_writeable($destination . '/')) {
        $entries = $rar->getEntries();
        foreach ($entries as $entry) {
          $entry->extract($destination);
        }
        $rar->close();
        $GLOBALS['status'] = array('success' => 'Soubory byly úspěšně extrahovány.');
      }
      else {
        $GLOBALS['status'] = array('error' => 'Chyba: Do adresáře nelze zapisovat webovým serverem.');
      }
    }
    else {
      $GLOBALS['status'] = array('error' => 'Chyba: Nelze přečíst archiv .rar.');
    }
  }

}

class Zipper {
  
  private static function folderToZip($folder, &$zipFile, $exclusiveLength) {
    $handle = opendir($folder);

    while (FALSE !== $f = readdir($handle)) {
      // Zkontroluje místní/nadřazenou cestu nebo samotný komprimovaný soubor a přeskočí.
      if ($f != '.' && $f != '..' && $f != basename(__FILE__)) {
        $filePath = "$folder/$f";
        // Před přidáním do zipu z cesty k souboru odstraní předponu.
        $localPath = substr($filePath, $exclusiveLength);

        if (is_file($filePath)) {
          $zipFile->addFile($filePath, $localPath);
        }
        elseif (is_dir($filePath)) {
          // Přidá podadresář.
          $zipFile->addEmptyDir($localPath);
          self::folderToZip($filePath, $zipFile, $exclusiveLength);
        }
      }
    }
    closedir($handle);
  }

  public static function zipDir($sourcePath, $outZipPath) {
    $pathInfo = pathinfo($sourcePath);
    $parentPath = $pathInfo['dirname'];
    $dirName = $pathInfo['basename'];

    $z = new ZipArchive();
    $z->open($outZipPath, ZipArchive::CREATE);
    $z->addEmptyDir($dirName);
    if ($sourcePath == $dirName) {
      self::folderToZip($sourcePath, $z, 0);
    }
    else {
      self::folderToZip($sourcePath, $z, strlen("$parentPath/"));
    }
    $z->close();

    $GLOBALS['status'] = array('success' => 'Archiv byl úspěšně vytvořen ' . $outZipPath);
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Rozbalení souboru + zip</title>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <style type="text/css">
    <!--
    body {
      font-family: Arial, sans-serif;
      line-height: 150%;
    }

    label {
      display: block;
      margin-top: 20px;
    }

    fieldset {
      border: 0;
      background-color: #EEE;
      margin: 10px 0 10px 0;
    }

    .select {
      padding: 5px;
      font-size: 110%;
    }

    .status {
      margin: 0;
      margin-bottom: 20px;
      padding: 10px;
      font-size: 80%;
      background: #EEE;
      border: 1px dotted #DDD;
    }

    .status--ERROR {
      background-color: red;
      color: white;
      font-size: 120%;
    }

    .status--SUCCESS {
      background-color: green;
      font-weight: bold;
      color: white;
      font-size: 120%
    }

    .small {
      font-size: 0.7rem;
      font-weight: normal;
    }

    .version {
      font-size: 80%;
    }

    .form-field {
      border: 1px solid #AAA;
      padding: 8px;
      width: 280px;
    }

    .info {
      margin-top: 0;
      font-size: 80%;
      color: #777;
    }

    .submit {
      background-color: #378de5;
      border: 0;
      color: #ffffff;
      font-size: 15px;
      padding: 10px 24px;
      margin: 20px 0 20px 0;
      text-decoration: none;
    }

    .submit:hover {
      background-color: #2c6db2;
      cursor: pointer;
    }
    -->
  </style>
</head>
<body>
<p class="status status--<?php echo strtoupper(key($GLOBALS['status'])); ?>">
  Stav: <?php echo reset($GLOBALS['status']); ?><br/>
  <span class="small">Časový proces: <?php echo $time; ?> sekundy</span>
</p>
<form action="" method="POST">
  <fieldset>
    <h1>Rozbalit adresář</h1>
    <label for="zipfile">Vybrat .zip nebo .rar archiv nebo .gz soubor, který chcete extrahovat:</label>
    <select name="zipfile" size="1" class="select">
      <?php foreach ($unzipper->zipfiles as $zip) {
        echo "<option>$zip</option>";
      }
      ?>
    </select>
    <label for="extpath">Extrakce cesty(volitelné):</label>
    <input type="text" name="extpath" class="form-field" />
    <p class="info">Zadejte extrakci cesty bez úvodních nebo koncových lomítek(e.g. "mypath"). Pokud zůstane prázdný, použije se aktuální adresář.</p>
    <input type="submit" name="dounzip" class="submit" value="Rozbalit archiv"/>
  </fieldset>

  <fieldset>
    <h1>Zabalit adresář</h1>
    <label for="zippath">Cesta, která by měla být zazipována (volitelné):</label>
    <input type="text" name="zippath" class="form-field" />
    <p class="info">Zadejte cestu, která se má zazipovat, bez úvodních nebo koncových lomítek (e.g. "zippath"). Pokud zůstane prázdný, použije se aktuální adresář.</p>
    <input type="submit" name="dozip" class="submit" value="Zazipovat archiv"/>
  </fieldset>
</form>
<p class="version">Verze: <?php echo VERSION; ?></p>
</body>
</html>
