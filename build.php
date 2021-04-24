<?php

Hurlus::init();
Hurlus::epub();
file_put_contents(dirname(__FILE__)."/README.md", Hurlus::readme());

class Hurlus
{
  
  public static function init()
  {
    
  }
  
  public static function epub()
  {
    include(dirname(dirname(__FILE__)).'/livrable/livrable.php');
    $kindlegen = dirname(dirname(__FILE__))."/livrable/kindlegen";
    $glob = dirname(dirname(__FILE__))."/hurlus-tei/*.xml";
    foreach (glob($glob) as $srcfile) {
      $dstpath = dirname(__FILE__).'/'.pathinfo($srcfile,  PATHINFO_FILENAME);
      $dstepub = $dstpath.".epub";
      if (file_exists($dstepub) && filemtime($dstepub) > filemtime($srcfile)) continue;
      echo $dstpath, "\n";
      $livre = new Livrable($srcfile, STDERR);
      $livre->epub($dstepub);
      $cmd = $kindlegen." ".$dstepub;
      $last = exec($cmd, $output, $status);
      // error ?
      $dstmobi = $dstpath.".mobi";
      if (!file_exists($dstmobi)) {
        self::log(E_USER_ERROR, "\n".$status."\n".join("\n", $output)."\n".$last."\n");
      }
    }
  }


  public static function readme()
  {
    include(dirname(dirname(__FILE__)).'/teinte/teidoc.php');
    $readme = "
# [Hurlus](https://hurlus.github.io/export/). Livres libres et classiques, pour nourrir les débats

";
    $glob = dirname(dirname(__FILE__))."/hurlus-tei/*.xml";
    $authorLast = '';
    $i = 1;
    foreach (glob($glob) as $srcfile) {
      $name = pathinfo($srcfile,  PATHINFO_FILENAME);
      preg_match('@^[^0-9_]+@', $name, $matches);
      $author = $matches[0];
      $teidoc = new Teidoc($srcfile);
      $meta = $teidoc->meta();
      if ($authorLast != $author) {
        $readme .= "\n## ".$meta['byline']."\n\n";
        $authorLast = $author;
      }
      // $readme .= '('.$i.')   ';
      if ($meta['date']) $readme .= $meta['date'].', ';
      $readme .= $meta['title'].' ';
      $readme .= ' <a class="mobi" href="https://hurlus.github.io/export/'.$name.'.mobi">[kindle]</a> ';
      $readme .= ' <a class="ebub" href="https://hurlus.github.io/export/'.$name.'.epub">[epub]</a> ';
      $readme .= "\n\n";
      $i++;
    }
    return $readme;
  }
  
  /** A logger, maybe a stream or a callable, used by self::log() */
  private static $_logger=STDERR;
  /**
   * Custom error handler
   * Especially used for xsl:message coming from transform()
   * To avoid Apache time limit, php could output some bytes during long transformations
   */
  static function log($errno, $errstr=null, $errfile=null, $errline=null, $errcontext=null)
  {
    $errstr=preg_replace("/XSLTProcessor::transform[^:]*:/", "", $errstr, -1, $count);
    if ($count) { // is an XSLT error or an XSLT message, reformat here
      if(strpos($errstr, 'error')!== false) return false;
      else if ($errno == E_WARNING) $errno = E_USER_WARNING;
    }
    // a debug message in normal mode, do nothing
    if ($errno == E_USER_NOTICE && !self::$debug) return true;
    // not a user message, let work default handler
    else if ($errno != E_USER_ERROR && $errno != E_USER_WARNING ) return false;
    if (!self::$_logger);
    else if (is_resource(self::$_logger)) fwrite(self::$_logger, $errstr."\n");
    else if ( is_string(self::$_logger) && function_exists(self::$_logger)) call_user_func(self::$_logger, $errstr);
  }
}


?>
