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
      $name = pathinfo($srcfile, PATHINFO_FILENAME);
      if ($name[0] == '_' || $name[0] == '.') continue;
      preg_match('@^(.*?)(_|\-\d|\d)@', $name, $matches);
      $author = $matches[1];
      $dstdir = dirname(__FILE__).'/'.$name.'/';
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
    $readme = '
# Hurlus, un catalogue bénévole <a href="#" onmouseover="if(this.ok)return; this.href=\'mai\'+\'lt\'+\'o:lire\'+\'\\u0040\'+\'hurlus.fr\'; this.ok=true">🖂</a>

> Des bouquinistes électroniques, pour du texte libre à participations libres

Chacun de ces textes a été aimé, ou haï, en tous cas a été lu, soigné, et parfois introduit d’une préface par une personne hurlue.
Elle s’y est intéressé parce qu’elle a pensé que ces pages étaient nécessaires,
nécessaires à sa réflexion du moment, à l’actualité, voire à l’intelligence de notre présent.
Ce catalogue n’obéit à aucun parti, ne milite pas pour une cause, sauf celle de réflechir et de partager
la matière de la réflexion.

Ne vous étonnez donc pas si Paul de Tarse côtoie Marx ou Descartes. Les textes religieux, par exemple, sont fondateurs de civilisations, il ne suffit pas de se dire athée pour les réfuter, il vaut mieux s’en informer pour lire jusqu’où ils influencent la société, en bien et en mal.
Les textes politiques, même ceux qui ne sont pas de notre
bord, continuent de marquer l’histoire. L’action des philosophes est plus souterraine, ils expriment souvent l’esprit de leur culture. Il y a aussi de l’histoire, des fictions, des livres longs pour les écrans, et des textes plus courts à imprimer et faire circuler.

L’édition électronique est soigneuse, tant sur la technique
que sur l’établissement du texte ; mais sans aucune prétention scolaire, au contraire.
Le but est de s’adresser à tous, sans distinction de science ou de diplôme, et d’attirer
ceux qui souhaitent découvrir cette autre manière de lire : éditer.

Chaque texte est diponible en plusieurs formats
\\ <b title="Source XML/TEI" class="mime48 tei">[TEI]</b> [XML/TEI](https://www.tei-c.org/release/doc/tei-p5-doc/en/html/REF-ELEMENTS.html), source depuis laquelle tous les format qui suivent sont générés
\\ <b title="EPUB, pour liseuses et téléphones" class="mime48 epub">[epub]</b> EPUB, livre électronique format ouvert (téléphones, liseuses…)
\\ <b title="HTML une page" class="mime48 html">[html]</b> HTML, texte à lire en une page
\\ <b title="Bureautique (LibreOffice, MS.Word)" class="mime48 docx">[docx]</b> DOCX, texte modifiable
\\ <b title="Amazon.kindle" class="mime48 mobi">[kindle]</b> MOBI, livre électronique au format propriétaire Kindle
\\ <b title="PDF à imprimer, A4 2 colonnes" class="mime48 pdf">[pdf]</b> PDF, A4 2 colonnes à imprimer
\\ <b title="PDF à lire, A5 une colonne" class="mime48 pdf">[pdf]</b> PDF, A5 1 colonne à lire
\\ <a title="PDF, brochure à agrafer, imposé pour imprimante recto/verso" class="mime48 brochure">[pdf]<b> PDF, brochure à agrafer, imposé pour imprimante recto/verso

';
    $authorLast = '';
    $i = 1;

    foreach (self::$publicfiles as $srcfile) {
      $name = pathinfo($srcfile,  PATHINFO_FILENAME);
      if ($name[0] == '_' || $name[0] == '.') continue;
      preg_match('@^(.*?)(_|\-\d|\d)@', $name, $matches);
      $author = $matches[1];
      $exportpath = dirname(__FILE__).'/'.$name.'/'.$name;
      $dstdir = 'https://hurlus.github.io/'.$name.'/';
      $dstpath = $dstdir.$name;
      $teidoc = new Teidoc($srcfile);
      $meta = $teidoc->meta();
      // order titles by author in catalog
      if ($authorLast != $author) {
        $authorLast = $author;
        if ($author == 'bible') $readme .= "\n## ". 'Bible'."\n\n";
        else $readme .= "\n## ".$meta['byline']."\n\n";
      }
      $bibl = '';
      if ($meta['date']) $bibl .= $meta['date'].', ';
      $bibl .= ' <a title="Source XML/TEI" class="mime tei" href="https://hurlus.github.io/tei/'.basename($srcfile).'">[TEI]</a> ';
      $bibl .= ' <a title="HTML une page" class="mime html" href="'.$dstpath.'.html">[html]</a> ';
      $bibl .= ' <a title="Bureautique (LibreOffice, MS.Word)" class="mime docx" href="'.$dstpath.'.docx">[docx]</a> ';
      $bibl .= ' <a title="Amazon.kindle" class="mime mobi" href="'.$dstpath.'.mobi">[kindle]</a> ';
      $bibl .= ' <a title="EPUB, pour liseuses et téléphones" class="mime epub" href="'.$dstpath.'.epub">[epub]</a> ';
      foreach (glob($exportpath."*.tex") as $exportfile) {
        $bibl .= ' <a title="LaTeX" class="mime tex" href="'.$dstdir.basename($exportfile).'">[TeX]</a> ';
      }
      foreach (glob($exportpath."*.pdf") as $exportfile) {
        if (strpos($exportfile, '_a5') !== false) {
          $bibl .= ' <a title="PDF à lire, A5 une colonne" class="mime a5" href="'.$dstdir.basename($exportfile).'">[pdf a5]</a> ';
        }
        else if (strpos($exportfile, '_brochure') !== false) {
          $bibl .= ' <a title="Brochure à agrafer, pdf imposé pour imprimante recto/verso" class="mime brochure" href="'.$dstdir.basename($exportfile).'">[brochure]</a> ';
        }
        else {
          $bibl .= ' <a title="PDF à imprimer, A4 2 colonnes" class="mime pdf" href="'.$dstdir.basename($exportfile).'">[pdf]</a> ';
        }
      }


      $readme .= $bibl . ' <a href="'.$dstdir.'">' . $meta['title']."</a>\n";
      // write a welcome page for the book
      $fopen = fopen(dirname(__FILE__).'/'.$name.'/README.md', 'w');
      fwrite($fopen, '# '.$meta['byline']."\n");
      fwrite($fopen, '## '.$meta['title']."\n\n");
      fwrite($fopen, '> '.str_replace('mime', 'mime48', $bibl)."\n");
      fclose($fopen);
      $i++;
    }
    $readme .= "
Les hurlus furent aussi des rebelles protestants qui cassaient les statues dans les églises catholiques. En 1566 démarra la révolte des gueux dans le pays de Lille. L’insurrection enflamma la région jusqu’à Anvers où les gueux de mer bloquèrent les bateaux espagnols.
Ce fut une rare guerre de libération dont naquit un pays toujours libre : les Pays-Bas.
En plat pays francophone, par contre, restèrent des bandes de huguenots, les hurlus, progressivement réprimés par la très catholique Espagne.
Cette mémoire d’une défaite est éteinte, rallumons-la. Sortons les livres du culte universitaire, cherchons les idoles de l’époque, pour les briser.
";
    return $readme;
  }
}

?>
