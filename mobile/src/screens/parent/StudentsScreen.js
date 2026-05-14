import React, { useState } from 'react';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import FormInput from '../../components/FormInput';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function StudentsScreen({ navigation }) {
  const { currentRole, students, updateStudent, refreshDashboard } = useAppContext();
  const [editingId, setEditingId] = useState(null);
  const [form, setForm] = useState({});
  const [refreshing, setRefreshing] = useState(false);
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
  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="students" />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow={isParent ? 'Student Accounts' : 'Assigned Students'}
        title={isParent ? 'Linked students under the parent account.' : 'Children assigned to this driver route.'}
        subtitle={isParent ? 'Each student should have a unique LRN and route information before ride booking.' : 'Drivers can review route details here, while profile ownership stays with the parents.'}
      />

      {students.map((student) => (
        <SectionCard key={student.id} title={student.name} subtitle={`${student.schoolName} - ${student.gradeLevel}`} icon="child">
          {editingId === student.id ? (
            <>
              <FormInput label="Student Name" value={form.studentName} onChangeText={(value) => updateField('studentName', value)} placeholder="Student name" />
              <FormInput label="LRN" value={form.lrn} onChangeText={(value) => updateField('lrn', value)} placeholder="LRN" />
              <FormInput label="School Name" value={form.schoolName} onChangeText={(value) => updateField('schoolName', value)} placeholder="School" />
              <FormInput label="Grade Level" value={form.gradeLevel} onChangeText={(value) => updateField('gradeLevel', value)} placeholder="Grade level" />
              <FormInput label="Pickup Address" value={form.pickupAddress} onChangeText={(value) => updateField('pickupAddress', value)} placeholder="Pickup address" multiline />
              <FormInput label="Drop-off Address" value={form.dropoffAddress} onChangeText={(value) => updateField('dropoffAddress', value)} placeholder="Drop-off address" multiline />
              <FormInput label="New Password" value={form.password} onChangeText={(value) => updateField('password', value)} placeholder="Leave blank to keep current password" secureTextEntry />
              <AppButton icon="save" label="Save Student" onPress={() => saveStudent(student.id)} />
              <AppButton icon="times" label="Cancel" variant="ghost" onPress={() => setEditingId(null)} />
            </>
          ) : (
            <>
              <Pill label={`LRN: ${student.lrn}`} />
              <InfoRow icon="map-marker-alt" label="Pickup" value={student.pickupAddress} />
              <InfoRow icon="flag-checkered" label="Drop-off" value={student.dropoffAddress} />
              <InfoRow icon="phone-alt" label="Emergency Contact" value={student.emergencyContact} />
              {isParent ? <AppButton icon="edit" label="Edit Student" variant="ghost" onPress={() => startEdit(student)} /> : null}
            </>
          )}
        </SectionCard>
      ))}

      {isParent ? <AppButton icon="user-plus" label="Add Student Account" onPress={() => navigation.navigate('AddStudent')} /> : null}
    </Screen>
  );
}
