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
          {else}
            <li>{$k1}</li>
          {/if}
          {call name=printList items=$v1}
        {else}
            {if is_numeric($k1)}
              <li>{$v1}</li>
            {else}
              <li>Question :{$k1}<br>Answer : {$v1}</li>
            {/if}
        {/if}
      {/foreach}
      </ol>
    {/function}

    <strong><h2>Arrowhead Policy Answered Questions:</h2></strong>
    {assign var=decodedData value=$data|json_decode:true}
    <ol>
        {foreach from=$decodedData key=k item=v}
        {if !(is_array($v)) }
          <li>Question : {$k}<br>Answer : {$v}</li>
        {else}
          <li>{$k}</li>
          {call name=printList items=$v}
        {/if}
      {/foreach}
    </ol>
  </body>
</html>
