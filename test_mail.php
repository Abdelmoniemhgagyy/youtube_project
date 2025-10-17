<?php
$to = "moniemhgagy@gmail.com";
$subject = "اختبار دالة mail()";
$message = "لو وصلك الإيميل ده، يبقى دالة mail() شغالة.";
$headers = "From: Test <noreply@yourdomain.com>\r\n";

if (mail($to, $subject, $message, $headers)) {
    echo "✅ تم إرسال الإيميل بنجاح";
} else {
    echo "❌ فشل إرسال الإيميل - السيرفر لا يدعم mail()";
}
?>

