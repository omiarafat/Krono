# Krono — Projects পেজ (design + dynamic)

এই zip-এর ফাইলগুলো একই পাথে কপি করে replace করুন (নতুন ডাটাবেস টেবিল লাগবে না, `migrate.php` আবার চালানোর দরকার নেই)।

## নতুন
- app/Project.php — নিয়ম: লিস্ট, সার্চ, ফিল্টার, সর্ট, পেজিনেশন, create/edit/archive, validation
- app/ProjectController.php
- app/Views/projects/index.php — কার্ড গ্রিড, filter row, Create/Edit modal, Archive confirmation, Discard unsaved changes, toast, empty/no-results/error অবস্থা

## পরিবর্তিত
- public/index.php (routes: /projects, /projects/save, /projects/archive, /project?id=)
- app/icons.php, app/bootstrap.php (flash-এ টাইটেল), resources/css/site.css + app.css (Tailwind token/component), public/assets/css/site.css (build করা)

## নিয়ম
- Owner/Admin/Manager: সব প্রজেক্ট দেখে + create/edit/archive করে।
- Member/Viewer: শুধু নিজের (তৈরি/লিডার/মেম্বার) প্রজেক্ট দেখে, বাটন নেই; সার্ভারেও আটকানো।
- Progress = completed task ÷ মোট task। Logged = প্রজেক্টের সব saved time log-এর যোগফল।
- Archive হলে tasks/logs থেকে যায়, কিছু delete হয় না। "Archived" ফিল্টারে দেখা যায়।
- কার্ডে ক্লিক → Project Details (পরের পেজ; এখন Coming soon, কিন্তু access চেক করে)।
