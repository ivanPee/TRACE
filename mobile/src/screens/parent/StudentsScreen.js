import React, { useState } from 'react';
import { Text } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import FormInput from '../../components/FormInput';
import HeaderBlock from '../../components/HeaderBlock';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function StudentsScreen({ navigation }) {
  const { currentRole, students, updateStudent } = useAppContext();
  const [editingId, setEditingId] = useState(null);
  const [form, setForm] = useState({});
  const isParent = currentRole === 'parent';
  const startEdit = (student) => {
    setEditingId(student.id);
    setForm({
      studentName: student.name || '',
      lrn: student.lrn || '',
      schoolName: student.schoolName || '',
      gradeLevel: student.gradeLevel || '',
      pickupAddress: student.pickupAddress || '',
      dropoffAddress: student.dropoffAddress || '',
      notes: student.notes || '',
      password: '',
    });
  };
  const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }));
  const saveStudent = async (studentId) => {
    await updateStudent(studentId, form);
    setEditingId(null);
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="students" />}>
      <HeaderBlock
        eyebrow={isParent ? 'Student Accounts' : 'Assigned Students'}
        title={isParent ? 'Linked students under the parent account.' : 'Children assigned to this driver route.'}
        subtitle={isParent ? 'Each student should have a unique LRN and route information before ride booking.' : 'Drivers can review route details here, while profile ownership stays with the parents.'}
      />

      {students.map((student) => (
        <SectionCard key={student.id} title={student.name} subtitle={`${student.schoolName} - ${student.gradeLevel}`}>
          {editingId === student.id ? (
            <>
              <FormInput label="Student Name" value={form.studentName} onChangeText={(value) => updateField('studentName', value)} placeholder="Student name" />
              <FormInput label="LRN" value={form.lrn} onChangeText={(value) => updateField('lrn', value)} placeholder="LRN" />
              <FormInput label="School Name" value={form.schoolName} onChangeText={(value) => updateField('schoolName', value)} placeholder="School" />
              <FormInput label="Grade Level" value={form.gradeLevel} onChangeText={(value) => updateField('gradeLevel', value)} placeholder="Grade level" />
              <FormInput label="Pickup Address" value={form.pickupAddress} onChangeText={(value) => updateField('pickupAddress', value)} placeholder="Pickup address" multiline />
              <FormInput label="Drop-off Address" value={form.dropoffAddress} onChangeText={(value) => updateField('dropoffAddress', value)} placeholder="Drop-off address" multiline />
              <FormInput label="New Password" value={form.password} onChangeText={(value) => updateField('password', value)} placeholder="Leave blank to keep current password" secureTextEntry />
              <AppButton label="Save Student" onPress={() => saveStudent(student.id)} />
              <AppButton label="Cancel" variant="ghost" onPress={() => setEditingId(null)} />
            </>
          ) : (
            <>
              <Pill label={`LRN: ${student.lrn}`} />
              <Text>Pickup: {student.pickupAddress}</Text>
              <Text>Drop-off: {student.dropoffAddress}</Text>
              <Text>Emergency Contact: {student.emergencyContact}</Text>
              {isParent ? <AppButton label="Edit Student" variant="ghost" onPress={() => startEdit(student)} /> : null}
            </>
          )}
        </SectionCard>
      ))}

      {isParent ? <AppButton label="Add Student Account" onPress={() => navigation.navigate('AddStudent')} /> : null}
    </Screen>
  );
}
