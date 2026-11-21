<?php
/**
 * What goes around every HTML message, until the config names another
 * layout.
 *
 * $content  the message, HTML that is ready to print
 * $subject  the subject, plain text
 *
 * A message is not a web page: mail programs ignore style sheets, so
 * the styles are written on the elements.
 */
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo HTML::chars($subject) ?></title>
</head>
<body style="margin:0;padding:24px;background:#f6f3ef;color:#33383b;font:15px/1.5 system-ui,-apple-system,'Segoe UI',Roboto,sans-serif">
<div style="max-width:600px;margin:0 auto;padding:24px;background:#fffdfa;border:1px solid #e3ddd5;border-radius:8px">
<?php echo $content ?>

</div>
</body>
</html>
