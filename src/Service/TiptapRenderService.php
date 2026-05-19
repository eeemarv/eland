<?php declare(strict_types=1);

namespace App\Service;

class TiptapRenderService
{
	public function __construct(
	)
	{
	}

  public function render(
    string $json,
  ): string
  {
    $data = json_decode($json, true);

    if (!$data || !isset($data['content']))
    {
      return '';
    }

    return $this->renderContent($data['content']);
  }

  /**
   * Handles an array of content (the children of a node)
   */
  private function renderContent(
    array $content
  ): string
  {
    $html = '';
    foreach ($content as $node)
    {
      $html .= $this->renderNode($node);
    }
    return $html;
  }

  /**
   * Handles an individual node
   */
  private function renderNode(
    array $node
  ): string
  {
    $type = $node['type'] ?? '';

    // 1. Handle Text Nodes (The base case for recursion)
    if ($type === 'text')
    {
      return $this->applyMarks($node['text'] ?? '', $node['marks'] ?? []);
    }

    // 2. Handle Structural Nodes
    switch ($type)
    {
      case 'paragraph':
        return '<p>' . $this->renderContent($node['content'] ?? []) . '</p>';

      case 'heading':
        $level = $node['attrs']['level'] ?? 1;
        return "<h{$level}>" . $this->renderContent($node['content'] ?? []) . "</h{$level}>";

      case 'bulletList':
        return '<ul>' . $this->renderContent($node['content'] ?? []) . '</ul>';

      case 'orderedList':
        return '<ol>' . $this->renderContent($node['content'] ?? []) . '</ol>';

      case 'listItem':
        return '<li>' . $this->renderContent($node['content'] ?? []) . '</li>';

      case 'image':
        $src = $node['attrs']['src'] ?? '';
        $alt = $node['attrs']['alt'] ?? '';
        return sprintf('<img src="%s" alt="%s">', $src, $alt);

      // Add more cases (bold, italic, etc. are usually handled in the 'text' node
      // if you use a custom schema, but standard Tiptap uses Marks)

      default:
        return '';
    }
  }

  /**
   * Handles the "Marks" (Bold, Italic, Link) which wrap text
   */
  private function applyMarks(string $text, array $marks): string
  {
    foreach ($marks as $mark)
    {
      switch ($mark['type'])
      {
        case 'bold':
          $text = "<strong>$text</strong>";
          break;
        case 'italic':
          $text = "<em>$text</em>";
          break;
        case 'link':
          $url = $mark['attrs']['href'] ?? '#';
          $text = "<a href=\"$url\">$text</a>";
          break;
      }
    }
    return $text;
  }

  /*
  // Helper to keep the loop clean
  private function renderContent(array $content): string
  {
      $html = '';
      foreach ($content as $node) {
          $html .= $this->renderNode($node);
      }
      return $html;
  }
      */

}
