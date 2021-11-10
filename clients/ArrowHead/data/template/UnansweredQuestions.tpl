<!DOCTYPE html>
<html>
  <head>
    <meta name="viewport" content="width=device-width" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Arrowhead AutoDealer</title>
    <link href= "{$smarty.current_dir}/css/unanswered_questions.css" rel="stylesheet" type="text/css" />
  </head>
  <body>

    {function name=printList}
      <ol>
      {foreach from=$items key=k1 item=v1}
        {if (is_array($v1))}
          {if is_numeric($k1) }
            ROW {$k1+1}
          {/if}
          {if (isset($v1['label']))}
            <li>{$v1['label']}</li>
          {/if}
          {call name=printList items=$v1}
        {else}
          {if !($k1 == 'label')}
            <li>{$v1}</li>
          {/if}
        {/if}
      {/foreach}
      </ol>
    {/function}

    {function name=printSection}
      <ol>
        {foreach from=$list key=k item=v}
        {if !(is_array($v)) }
          <li>{$v}</li>
        {else}
          <li>{$v['label']}</li>
          {call name=printList items=$v}
        {/if}
      {/foreach}
      </ol>
    {/function}

    <strong><h1>Unanswered Questions</h1></strong>
    <strong><h2>Arrowhead Policy Required Questions:</h2></strong>
    {assign var=decodedData value=$data|json_decode:true}
    {$list = $decodedData['requiredUnansweredQuestions']}
    {call name=printSection items=$list}
    <strong><h2>Arrowhead Policy Remaining Non-Required Questions:</h2></strong>
    {$list = $decodedData['unansweredQuestions']}
    {call name=printSection items=$list}
  </body>
</html>
