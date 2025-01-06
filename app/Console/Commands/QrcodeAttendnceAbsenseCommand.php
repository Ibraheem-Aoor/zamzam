<?php

namespace App\Console\Commands;

use App\SmStudent;
use App\SmStudentAttendance;
use App\Models\StudentRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Modules\QRCodeAttendance\Entities\QRCodeAttendanceSetting;

class QrcodeAttendnceAbsenseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'qrcode:attendance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Absense Status';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        date_default_timezone_set(timeZone());
        $schools = DB::table('sm_schools')->where('active_status',1)->get();
        foreach($schools as $school)
        {
            $settings = QRCodeAttendanceSetting::whereTime('time','<', date("H:i:s"))->where('school_id',$school->id)->select('id')->get();
            foreach($settings as $setting)
            {
                $hasAttendance   = SmStudentAttendance::where('attendance_date',date("Y-m-d"))
                                                    ->where('class_id',$setting->class_id)
                                                    ->where('section_id',$setting->section_id)
                                                    ->where('school_id',$setting->school_id)
                                                    ->where('attendance_type','P')
                                                    ->where('school_id',$school->id)
                                                    ->select('student_id')
                                                    ->get()->toArray();
                if(count($hasAttendance))
                {
                    
                    $students = StudentRecord::where('class_id',$setting->class_id)
                                            ->where('section_id',$setting->section_id)
                                            ->whereNotIn('student_id',$hasAttendance)
                                            ->where('school_id',$setting->school_id)
                                            ->where('school_id',$school->id)
                                            ->select(['student_id'])
                                            ->get();
                    foreach($students as $student)
                    {
                        $data =  [
                            'student_id' => $student->student_id,
                            'class_id' => $setting->class_id,
                            'section_id' => $setting->section_id,
                        ];
                        $this->studentAttendance($data);
                    }
                }
            }
        }
        
        $this->info('Success');
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
           
        ];
    }

    protected function studentAttendance($data)
    {
        
        $student = StudentRecord::where('student_id', $data['student_id'])->with(['student','smClass','section'])->first();
        if($student)
        {
            $hasOne = SmStudentAttendance::where('student_id',$data['student_id'])->where('attendance_date',date("Y-m-d"))->first();
            if(!$hasOne)
            {
                SmStudentAttendance::create([
                    "attendance_type" => 'A',
                    "attendance_date" => date("Y-m-d"),
                    "student_id" => $student->student_id,
                    "student_record_id" => $student->id,
                    "class_id" => $data['class_id'],
                    "section_id" => $data['section_id'],
                    "school_id" => $student->school_id,
                    "academic_id" => $student->academic_id,
                    "source" => "qr_code"
                ]);

                //Sms Notification
                $student_info = SmStudent::find($student->student_id);
                $compact['attendance_date'] = date("Y-m-d");
                $compact['user_email'] = $student_info->email;
                $compact['student_id'] = $student_info;
                @send_sms($student_info->mobile, 'student_absent', $compact);
                $compact['user_email'] = @$student_info->parents->guardians_email;
                @send_sms(@$student_info->parents->guardians_mobile, 'student_absent_for_parent', $compact);
                
                return true;

            }else{
                return false;
            }

        }else{
            return false;
        }
        
        return false;
    }
}
