  </div>
  <div class="clearer"></div>
 </div>

 <?php
if ($s_accountrole == 'admin'){
	echo '<div><p><b>Support mailinglijst</b>';
	echo '<ul><li>Inschrijven: support-elas-heroku-subscribe@lists.riseup.net</li>';
	echo '<li>Berichten posten:  support-elas-heroku@lists.riseup.net</li></ul></p>';
	echo '<p><b>Rapporteer bugs in de <a href="https://github.com/eeemarv/elas-heroku/issues">Github issue tracker</a>.</b> (Maak eerst een <a href="https://github.com">Github</a> account aan.)</p>
	</div>';
}
 ?>

 <div id="footer">
	<div id="footerleft">
	<?php
	if(isset($s_id)){
		echo $s_name." (".trim($s_letscode)."), ";
		echo " <a href='".$rootpath."logout.php'>Uitloggen</a>";
	}
	?>
	</div>
	<div id="footerright">
	<a href="https://github.com/eeemarv/elas-heroku ">eLAS-Heroku</a>
	</div>
</div>




<?php
if (isset($includejs)) {
	echo $includejs;
}
?>

</body>
</html>
