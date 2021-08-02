<?php
$teinte = dirname(dirname(__FILE__)).'/teinte/';
include_once($teinte.'php/tools.php');
include_once($teinte.'hurlus/hurlus.php');
include_once($teinte.'docx/docx.php');
include_once($teinte.'epub/epub.php');

HurlusBuild::init();
HurlusBuild::export();
file_put_contents(dirname(__FILE__)."/README.md", HurlusBuild::readme());

class HurlusBuild
{

  static $publicfiles;
  static $privatefiles;
  public static function init()
  {
    self::$publicfiles = glob(dirname(dirname(__FILE__))."/hurlus-tei/*.xml");
    self::$privatefiles = glob(dirname(dirname(__FILE__))."/hurlus-private/*.xml");
    // self::$srclist = array_merge([], ...array_values($arrays)); // remember
  }



  public static function export()
  {
    $kindlegen = dirname(dirname(__FILE__))."/teinte/epub/kindlegen";
    foreach (array_merge(self::$publicfiles, self::$privatefiles) as $srcfile) {
      $name = pathinfo($srcfile,  PATHINFO_FILENAME);
      if ($name[0] == '_' || $name[0] == '.') continue;
      preg_match('@^(.*?)(_|\-\d|\d)@', $name, $matches);
      $author = $matches[1];
      $dstdir = dirname(__FILE__).'/'.$author.'/';
      Tools::mkdir($dstdir);
      $dstpath = $dstdir.$name;

      $done = false;

      $dstepub = $dstpath.".epub";
      if (!file_exists($dstepub) || filemtime($dstepub) < filemtime($srcfile)) {
        $livre = new Epub($srcfile, STDERR);
        $livre->export($dstepub);
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
      if (!file_exists($dstfile) || filemtime($dstfile) < filemtime($srcfile)) {
        $done = true;
        self::html($srcfile, $dstfile);
        // test pdf for new file only
        Hurlus::pdf($srcfile, $dstdir);
      }
      $dstfile = $dstpath.".docx";
      if (!file_exists($dstfile) || filemtime($dstfile) < filemtime($srcfile)) {
        $done = true;
        Docx::export($srcfile, $dstfile);
      }



      if ($done) echo $dstpath, "\n";
    }
  }

  /**
   * Output html
   */
  public function html($srcfile, $dstfile)
  {
    $theme = 'https://oeuvres.github.io/teinte/'; // where to find web assets like css and jslog for html file
    $xsl = dirname(dirname(__FILE__)).'/teinte/tei2html.xsl';
    $dom = Tools::dom($srcfile);
    $pars = array(
      'theme' => $theme,
    );
    Tools::transformDoc($dom, $xsl, $dstfile, $pars);
  }


  public static function readme()
  {
    include_once(dirname(dirname(__FILE__)).'/teinte/teidoc.php');
    $readme = "
# Auteurs / titres

";
    $authorLast = '';
    $i = 1;

    $fauth = null;
    $authbib = '';
    foreach (self::$publicfiles as $srcfile) {
      $name = pathinfo($srcfile,  PATHINFO_FILENAME);
      if ($name[0] == '_' || $name[0] == '.') continue;
      preg_match('@^(.*?)(_|\-\d|\d)@', $name, $matches);
      $author = $matches[1];
      $exportpath = dirname(__FILE__).'/'.$author.'/'.$name;
      $dstdir = 'https://hurlus.github.io/'.$author.'/';
      $dstpath = $dstdir.$name;
      $teidoc = new Teidoc($srcfile);
      $meta = $teidoc->meta();
      if ($authorLast != $author) {
        if ($author == 'bible') $meta['byline'] = 'Bible';
        $fopen = dirname(__FILE__).'/'.$author.'/README.md';
        $fauth = fopen($fopen, "w");
        $readme .= "\n## ".'<a href="'.$author.'/">'.$meta['byline']."</a>\n\n";
        fwrite($fauth, '# '.$meta['byline']."\n\n");
        $authorLast = $author;
      }
      $authbib = '* ';
      if ($meta['date']) $authbib .= $meta['date'].', ';
      $authbib .= ' <a title="Source XML/TEI" class="file tei" href="https://hurlus.github.io/tei/'.basename($srcfile).'">[TEI]</a> ';
      $authbib .= ' <a title="HTML une page" class="file html" href="'.$dstpath.'.html">[html]</a> ';
      $authbib .= ' <a title="Bureautique (LibreOffice, MS.Word)" class="file docx" href="'.$dstpath.'.docx">[docx]</a> ';
      $authbib .= ' <a title="Amazon.kindle" class="file mobi" href="'.$dstpath.'.mobi">[kindle]</a> ';
      $authbib .= ' <a title="EPUB, pour liseuses et téléphones" class="file epub" href="'.$dstpath.'.epub">[epub]</a> ';
      foreach (glob($exportpath."*.tex") as $exportfile) {
        $authbib .= ' <a title="LaTeX" class="file tex" href="'.$dstdir.basename($exportfile).'">[TeX]</a> ';
      }
      foreach (glob($exportpath."*.pdf") as $exportfile) {
        if (strpos($exportfile, '_a5') !== false) {
          $authbib .= ' <a title="PDF à lire, A5 une colonne" class="file a5" href="'.$dstdir.basename($exportfile).'">[pdf a5]</a> ';
        }
        else if (strpos($exportfile, '_brochure') !== false) {
          $authbib .= ' <a title="Brochure à agrafer, pdf imposé pour imprimante recto/verso" class="file brochure" href="'.$dstdir.basename($exportfile).'">[brochure]</a> ';
        }
        else {
          $authbib .= ' <a title="PDF à imprimer, A4 2 colonnes" class="file pdf" href="'.$dstdir.basename($exportfile).'">[pdf]</a> ';
        }
      }


      $authbib .= ' <a href="'.$dstpath.'.html">' . $meta['title'].'</a>';
      $authbib .= "\n";
      $i++;
      $readme .= $authbib;
      fwrite($fauth, $authbib);
    }
    return $readme;
  }
}

?>
