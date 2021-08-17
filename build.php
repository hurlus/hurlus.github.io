<?php
$teinte = dirname(dirname(__FILE__)).'/teinte/';
include_once($teinte.'php/tools.php');
include_once($teinte.'hurlus/hurlus.php');
include_once($teinte.'docx/docx.php');
include_once($teinte.'epub/epub.php');

HurlusBuild::init();
if (isset($_SERVER['argv'][1])) HurlusBuild::export($_SERVER['argv'][1], true);
else HurlusBuild::loop();
file_put_contents(dirname(__FILE__)."/README.md", HurlusBuild::readme());

class HurlusBuild
{

  static $publicfiles;
  static $privatefiles = array();
  public static function init()
  {
    self::$publicfiles = glob(dirname(dirname(__FILE__))."/hurlus-tei/*.xml");
    // TODO, send private else where
    // self::$privatefiles = glob(dirname(dirname(__FILE__))."/hurlus-private/*.xml");
    // self::$srclist = array_merge([], ...array_values($arrays)); // remember
  }

  public static function loop()
  {
    foreach (array_merge(self::$publicfiles, self::$privatefiles) as $teifile) {
      self::export($teifile);
    }
  }


  public static function export($teifile, $force=false)
  {
    $teifile = realpath($teifile);
    $name = pathinfo($teifile, PATHINFO_FILENAME);
    if ($name[0] == '_' || $name[0] == '.') return;
    preg_match('@^(.*?)(_|\-\d|\d)@', $name, $matches);
    $author = $matches[1];
    $dstdir = dirname(__FILE__).'/'.$name.'/';
    Tools::mkdir($dstdir);
    $dstpath = $dstdir.$name;

    $done = false;

    $dstepub = $dstpath.".epub";
    if ($force || !file_exists($dstepub) || filemtime($dstepub) < filemtime($teifile)) {
      $livre = new Epub($teifile, STDERR);
      $livre->export($dstepub);
      $kindlegen = dirname(dirname(__FILE__))."/teinte/epub/kindlegen";
      $cmd = $kindlegen." ".$dstepub;
      $output = '';
      $last = exec($cmd, $output, $status);
      // error ?
      $dstmobi = $dstpath.".mobi";
      if (!file_exists($dstmobi)) {
        Tools::log(E_USER_ERROR, "\n".$status."\n".join("\n", $output)."\n".$last."\n");
      }
      $done = true;
    }
    $dstfile = $dstpath.".html";
    if ($force || !file_exists($dstfile) || filemtime($dstfile) < filemtime($teifile)) {
      $done = true;
      self::html($teifile, $dstfile);
    }
    $dstfile = $dstpath.".pdf";
    if ($force || !file_exists($dstfile) || filemtime($dstfile) < filemtime($teifile)) {
      Hurlus::pdf($teifile, $dstdir);
    }
    $dstfile = $dstpath.".docx";
    if ($force || !file_exists($dstfile) || filemtime($dstfile) < filemtime($teifile)) {
      $done = true;
      Docx::export($teifile, $dstfile);
    }
    if ($done) echo $dstpath, "\n";
  }

  /**
   * Output html
   */
  public function html($teifile, $dstfile)
  {
    $theme = 'https://oeuvres.github.io/teinte/'; // where to find web assets like css and jslog for html file
    $xsl = dirname(dirname(__FILE__)).'/teinte/tei2html.xsl';
    $dom = Tools::dom($teifile);
    $pars = array(
      'theme' => $theme,
    );
    Tools::transformDoc($dom, $xsl, $dstfile, $pars);
  }


  public static function readme()
  {
    include_once(dirname(dirname(__FILE__)).'/teinte/teidoc.php');
    $readme = '';
    $authorLast = '';
    $i = 1;

    foreach (self::$publicfiles as $teifile) {
      $name = pathinfo($teifile,  PATHINFO_FILENAME);
      if ($name[0] == '_' || $name[0] == '.') continue;
      preg_match('@^(.*?)(_|\-\d|\d)@', $name, $matches);
      $author = $matches[1];
      $exportpath = dirname(__FILE__).'/'.$name.'/'.$name;
      $dstdir = 'https://hurlus.github.io/'.$name.'/';
      $dstpath = $dstdir.$name;
      $teidoc = new Teidoc($teifile);
      $meta = $teidoc->meta();
      // order titles by author in catalog
      if ($authorLast != $author) {
        $authorLast = $author;
        if ($author == 'bible') $readme .= "\n## ". 'Bible'."\n\n";
        else $readme .= "\n## ".$meta['byline']."\n\n";
      }
      $bibl = '';
      $bibl .= ' <a target="_blank" title="Source XML/TEI" class="mime tei" href="https://hurlus.github.io/tei/'.basename($teifile).'">[TEI]</a> ';
      $bibl .= ' <a target="_blank" title="HTML une page" class="mime html" href="'.$dstpath.'.html">[html]</a> ';
      $bibl .= ' <a target="_blank" title="Bureautique (LibreOffice, MS.Word)" class="mime docx" href="'.$dstpath.'.docx">[docx]</a> ';
      $bibl .= ' <a target="_blank" title="Amazon.kindle" class="mime mobi" href="'.$dstpath.'.mobi">[kindle]</a> ';
      $bibl .= ' <a target="_blank" title="EPUB, pour liseuses et téléphones" class="mime epub" href="'.$dstpath.'.epub">[epub]</a> ';
      foreach (glob($exportpath."*.tex") as $exportfile) {
        $bibl .= ' <a target="_blank" title="LaTeX" class="mime tex" href="'.$dstdir.basename($exportfile).'">[TeX]</a> ';
      }
      foreach (glob($exportpath."*.pdf") as $exportfile) {
        if (strpos($exportfile, '_a5') !== false) {
          $bibl .= ' <a target="_blank" title="PDF à lire, A5 une colonne" class="mime a5" href="'.$dstdir.basename($exportfile).'">[pdf a5]</a> ';
        }
        else if (strpos($exportfile, '_brochure') !== false) {
          $bibl .= ' <a target="_blank" title="Brochure à agrafer, pdf imposé pour imprimante recto/verso" class="mime brochure" href="'.$dstdir.basename($exportfile).'">[brochure]</a> ';
        }
        else {
          $bibl .= ' <a target="_blank" title="PDF à imprimer, A4 2 colonnes" class="mime pdf" href="'.$dstdir.basename($exportfile).'">[pdf]</a> ';
        }
      }


      // write a welcome page for the book
      $fopen = fopen(dirname(__FILE__).'/'.$name.'/README.md', 'w');
      fwrite($fopen, '# '.$meta['byline']);
      if ($meta['date']) fwrite($fopen, ', '.$meta['date']);
      fwrite($fopen, "\n\n");
      fwrite($fopen, '> ## '.$meta['title']."\n");
      fwrite($fopen, '> '.str_replace('mime', 'mime48', $bibl)."\n");
      fclose($fopen);

      if ($meta['date']) $bibl = $meta['date'].', '.$bibl;
      $readme .= '* '.$bibl . ' <a href="'.$dstdir.'">' . $meta['title']."</a>\n";


      $i++;
    }

    return str_replace('<!--catalog-->', $readme, file_get_contents(dirname(__FILE__).'/accueil.md'));
  }
}

?>
