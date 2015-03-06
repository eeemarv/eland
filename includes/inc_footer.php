  </div>
  <div class="clearer"></div>
 </div>

 <?php
if ($s_accountrole == 'admin'){
	echo '<div><p><b>Support mailinglijst</b>';
	echo '<ul><li>Inschrijven: support-elas-heroku-subscribe@lists.riseup.net</li>';
	echo '<li>Berichten posten:  support-elas-heroku@lists.riseup.net</li></ul></p></div>';
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

	<script type='text/javascript'>
	function OpenAboutBox() {
		TINY.box.show({url:'about.php', fixed:false,width:0})
	}
	</script>

	<?php
	echo "<a href='javascript: OpenAboutBox();'>eLAS v" .$elasversion ."</a>";
	?>
	</div>
</div>

</body>
</html>
