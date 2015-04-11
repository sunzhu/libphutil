<?php

final class XHPASTTree extends AASTTree {

  public function __construct(array $tree, array $stream, $source) {
    $this->setTreeType('XHP');
    $this->setNodeConstants(xhp_parser_node_constants());
    $this->setTokenConstants(xhpast_parser_token_constants());

    parent::__construct($tree, $stream, $source);
  }

  public function newNode($id, array $data, AASTTree $tree) {
    return new XHPASTNode($id, $data, $tree);
  }

  public function newToken($id, $type, $value, $offset, AASTTree $tree) {
    return new XHPASTToken($id, $type, $value, $offset, $tree);
  }

  public static function newFromData($php_source) {
    $future = PhutilXHPASTBinary::getParserFuture($php_source);
    return self::newFromDataAndResolvedExecFuture(
      $php_source,
      $future->resolve());
  }

  public static function newStatementFromString($string) {
    $string = '<?php '.rtrim($string, ';').";\n";
    $tree = XHPASTTree::newFromData($string);
    $statements = $tree->getRootNode()->selectDescendantsOfType('n_STATEMENT');
    if (count($statements) != 1) {
      throw new Exception(
        pht('String does not parse into exactly one statement!'));
    }
    // Return the first one, trying to use reset() with iterators ends in tears.
    foreach ($statements as $statement) {
      return $statement;
    }
  }

  public static function newFromDataAndResolvedExecFuture(
    $php_source,
    array $resolved) {

    list($err, $stdout, $stderr) = $resolved;
    if ($err) {
      if ($err == 1) {
        $matches = null;
        $is_syntax = preg_match(
          '/^XHPAST Parse Error: (.*) on line (\d+)/s',
          $stderr,
          $matches);
        if ($is_syntax) {
          throw new XHPASTSyntaxErrorException($matches[2], trim($stderr));
        }
      }
      throw new Exception(
        pht(
          'XHPAST failed to parse file data %d: %s',
          $err,
          $stderr));
    }

    $data = null;
    try {
      $data = phutil_json_decode($stdout);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht('XHPAST: failed to decode tree.'),
        $ex);
    }

    return new XHPASTTree($data['tree'], $data['stream'], $php_source);
  }

}
