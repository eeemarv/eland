<?php declare(strict_types=1);

namespace App\Command;

trait ArrayCleanConvertTrait
{
  public function get_clean_array(): array
  {
    $result_ary = [];
    $start_ary = get_object_vars($this);

    foreach ($start_ary as $key => $value)
    {
      if ($value === null || $value === false)
      {
        continue;
      }

      if (is_object($value) && method_exists($value, 'get_clean_array'))
      {
        $nested_ary = $value->get_clean_array();
        if (!empty($nested_ary))
        {
          $result_ary[$key] = $nested_ary;
        }
        continue;
      }

      if (is_array($value))
      {
        $filtered_ary = [];
        foreach ($value as $k => $v)
        {
          if ($v === null || $v === false)
          {
            continue;
          }
          $filtered_ary[$k] = $v;
        }
        if (!empty($filtered_ary))
        {
          $result_ary[$key] = $filtered_ary;
        }
        continue;
      }

      $result_ary[$key] = $value;
    }

    return $result_ary;
  }

  public function populate_from_array(array $ary): void
  {
    foreach ($ary as $key => $value)
    {
      if (!property_exists($this, $key))
      {
        continue;
      }

      if (isset($this->$key))
      {
        $current = $this->$key;

        if (is_object($current) && method_exists($current, 'populate_from_array') && is_array($value))
        {
          $current->populate_from_array($value);
          continue;
        }

        if (is_array($current) && is_array($value))
        {
          $this->$key = $value;
          continue;
        }
      }

      $this->$key = $value;
    }
  }
}
