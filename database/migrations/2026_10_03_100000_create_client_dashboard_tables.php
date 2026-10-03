<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Přehled spolupráce pro klienta na měsíční paušál: za co platí, kolik
 * hodin jsme odpracovali, co je hotové, co čeká na něj a co je v plánu.
 *
 * - client_retainers: paušál rozdělený po oblastech (vývoj webu, marketing),
 * - client_tasks: úkoly, ke kterým se zapisuje čas,
 * - client_months: cíl měsíce a náš komentář k němu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->date('started_on')->nullable()->after('note');                     // začátek pravidelné spolupráce
            $table->string('dashboard_token', 40)->nullable()->unique()->after('started_on'); // /klient/{token}
            $table->boolean('dashboard_enabled')->default(false)->after('dashboard_token');  // bez zapnutí odkaz vrací 404
        });

        Schema::create('client_retainers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('area', 20);                            // App\Enums\WorkArea
            $table->string('label');                               // „Vývoj webu“, klient ho vidí
            $table->unsignedInteger('monthly_fee');                // Kč za měsíc
            $table->decimal('included_hours', 5, 1)->nullable();   // null = hodiny bez stropu, jen je ukazujeme
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('order_column')->default(0);
            $table->timestamps();
        });

        Schema::create('client_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // kdo úkol vede
            $table->string('area', 20);                            // App\Enums\WorkArea
            $table->string('title');
            $table->text('description')->nullable();               // pro klienta: co a proč
            $table->text('internal_note')->nullable();             // jen pro nás, ven se nevykresluje
            $table->string('status', 20)->default('planned');      // App\Enums\TaskStatus
            $table->date('planned_for')->nullable();               // první den měsíce, kdy na úkol dojde
            $table->date('done_on')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });

        Schema::create('client_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('month');                                 // první den měsíce
            $table->string('goal')->nullable();                    // cíl měsíce, jedna věta
            $table->text('summary')->nullable();                   // co jsme zjistili, Markdown
            $table->timestamps();

            $table->unique(['client_id', 'month']);
        });

        Schema::table('time_entries', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->after('user_id')->constrained('client_tasks')->nullOnDelete();
            $table->string('area', 20)->nullable()->after('task_id'); // App\Enums\WorkArea, u úkolu se bere z něj
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_id');
            $table->dropColumn('area');
        });

        Schema::dropIfExists('client_months');
        Schema::dropIfExists('client_tasks');
        Schema::dropIfExists('client_retainers');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['dashboard_token']);
            $table->dropColumn(['started_on', 'dashboard_token', 'dashboard_enabled']);
        });
    }
};
