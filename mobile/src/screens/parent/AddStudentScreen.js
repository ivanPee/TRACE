import React, { useState } from 'react';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import FormInput from '../../components/FormInput';
import HeaderBlock from '../../components/HeaderBlock';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function AddStudentScreen({ navigation }) {
  const { addStudent } = useAppContext();
  const [form, setForm] = useState({
    studentName: '',
    lrn: '',
    schoolName: '',
    gradeLevel: '',
    pickupAddress: '',
    dropoffAddress: '',
    emergencyContact: '',
    notes: '',
  });

  const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }));

  const handleSave = () => {
    addStudent({
      studentName: form.studentName || 'New Student',
      lrn: form.lrn || `${Date.now()}`.slice(-12),
      schoolName: form.schoolName || 'School Name',
      gradeLevel: form.gradeLevel || 'Grade Level',
      pickupAddress: form.pickupAddress || 'Home address',
      dropoffAddress: form.dropoffAddress || 'School gate',
      emergencyContact: form.emergencyContact || 'Parent contact',
      notes: form.notes,
    });

    navigation.goBack();
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="students" />}>
      <HeaderBlock
        eyebrow="New Student"
        title="Create the student account from the parent side."
        subtitle="In the real backend this should validate that the LRN is unique before saving."
      />
      <SectionCard>
        <FormInput label="Student Name" value={form.studentName} onChangeText={(value) => updateField('studentName', value)} placeholder="Lia Villanueva" />
        <FormInput label="LRN" value={form.lrn} onChangeText={(value) => updateField('lrn', value)} placeholder="112233445566" />
        <FormInput label="School Name" value={form.schoolName} onChangeText={(value) => updateField('schoolName', value)} placeholder="School name" />
        <FormInput label="Grade Level" value={form.gradeLevel} onChangeText={(value) => updateField('gradeLevel', value)} placeholder="Grade 8" />
        <FormInput label="Pickup Address" value={form.pickupAddress} onChangeText={(value) => updateField('pickupAddress', value)} placeholder="Home address" multiline />
        <FormInput label="Drop-off Address" value={form.dropoffAddress} onChangeText={(value) => updateField('dropoffAddress', value)} placeholder="School gate" multiline />
        <FormInput label="Emergency Contact" value={form.emergencyContact} onChangeText={(value) => updateField('emergencyContact', value)} placeholder="Parent mobile number" />
        <FormInput label="Medical Notes" value={form.notes} onChangeText={(value) => updateField('notes', value)} placeholder="Optional notes" multiline />
        <AppButton label="Save Student Account" onPress={handleSave} />
      </SectionCard>
    </Screen>
  );
}
